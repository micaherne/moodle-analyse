<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

use Symfony\Component\Finder\SplFileInfo;

use function array_shift;
use function count;
use function implode;
use function preg_match_all;
use function strlen;
use function substr;

use const PREG_OFFSET_CAPTURE;

class ResolvedIncludeProcessor
{

    const QUOTE = '\'';

    public function categorise(string $resolvedInclude): ?string
    {
        if (preg_match('#^@[/\\\\]?$#', $resolvedInclude)) {
            return 'dirroot';
        } elseif ($resolvedInclude === '@/config.php') {
            return 'config';
        } elseif (preg_match('#^@[\d\w\-/.]+\.\w+$#', $resolvedInclude)) {
            return 'simple file';
        } elseif (preg_match('#^@[\d\w\-/]+/?$#', $resolvedInclude)) {
            return 'simple dir';
        } elseif (preg_match('#^{[^}{]+}$#', $resolvedInclude)) {
            // e.g. {$somevariable}
            return 'single var';
        } elseif (preg_match('#^@/?{[^}{]+}$#', $resolvedInclude)) {
            // e.g. @/{$somevariable}
            return 'full relative path';
        } elseif (preg_match('#^.+@#', $resolvedInclude)) {
            return 'suspect - embedded @';
        } elseif (preg_match('#\*#', $resolvedInclude)) {
            return 'glob';
        } elseif (preg_match('#^{[^}{]+}/[^}{]+\.\w+$#', $resolvedInclude)) {
            # e.g. {$fullblock}/db/install.php
            return 'fulldir relative';
        } elseif (preg_match('#^@/(([^/]*)/)*[^/}{]*\.\w+$#', $resolvedInclude)) {
            # e.g. {$fullblock}/db/install.php
            return 'simple dynamic file';
        } elseif (preg_match('#^@/[^}{]+/{[^}{]+}\.\w+$#', $resolvedInclude)) {
            # e.g. @/completion/criteria/{$object}.php
            return 'filename substitution';
        } else {
            return null;
        }
    }

    /**
     * Create a string of PHP code for a given resolved include.
     *
     * This is simply the "canonical" version of the path given.
     *
     * @param string $resolvedInclude
     * @return string
     */
    public function toCodeString(string $resolvedInclude, ?string $filePath = null): string
    {
        if (!is_null($filePath) && $resolvedInclude === '@/config.php') {
            return '__DIR__ . \'' . str_repeat('/..', substr_count($filePath, '/')) . '/config.php\'';
        }

        $resultParts = [];

        // Easier to split to avoid having to deal with partial words (e.g. $CFG->library)
        $includeParts = $this->splitResolvedInclude($resolvedInclude);

        // Easier to split
        if ($includeParts[0] === '@') {
            array_shift($includeParts);
            if (count($includeParts) === 0) {
                $resultParts[] = '$CFG->dirroot';
            } else {
                switch ($includeParts[0]) {
                    case 'lib':
                        $resultParts[] = '$CFG->libdir';
                        array_shift($includeParts);
                        break;

                    case 'admin':
                        $resultParts[] = '$CFG->dirroot';
                        $resultParts[] = '$CFG->admin';
                        array_shift($includeParts);
                        break;

                    default:
                        $resultParts[] = '$CFG->dirroot';
                }
            }
        } else {
            if (str_starts_with($includeParts[0], '@')) {
                // There's no slash after the dirroot symbol.
                $includeParts[0] = '{$CFG->dirroot}' . substr($includeParts[0], 1);
            }
        }

        // Extract variables from part.
        foreach ($includeParts as $includePart) {
            $matches = [];
            $partResultParts = [];
            $hasVariables = preg_match_all('#{(.+?)}#', $includePart, $matches, PREG_OFFSET_CAPTURE);
            if ($hasVariables) {
                // Find the variables and extract the string before, if there is one, and then the variable content.
                $currentPosition = 0;
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $startPosition = $matches[0][$i][1];
                    if ($startPosition > $currentPosition) {
                        $partResultParts[] = ResolvedIncludeProcessor::QUOTE . substr(
                                $includePart,
                                $currentPosition,
                                $startPosition - $currentPosition
                            ) . ResolvedIncludeProcessor::QUOTE;
                    }
                    $partResultParts[] = $matches[1][$i][0];
                    $currentPosition = $startPosition + strlen($matches[0][$i][0]);
                }

                // Add the string at the end if it's there.
                if ($currentPosition < strlen($includePart)) {
                    $partResultParts[] = ResolvedIncludeProcessor::QUOTE . substr(
                            $includePart,
                            $currentPosition
                        ) . ResolvedIncludeProcessor::QUOTE;
                }
                $resultParts[] = implode(' . ', $partResultParts);
            } else {
                $resultParts[] = ResolvedIncludeProcessor::QUOTE . $includePart . ResolvedIncludeProcessor::QUOTE;
            }
        }

        $result = implode(' . \'/\' . ', $resultParts);

        // Deal with backslashes escaping the quotes (mainly in @\ resolved path).
        // This is a bit shoddy and could be better.
        $result = str_replace('\\\'', '\\\\\'', $result);

        // Merge consecutive strings.
        return $this->mergeStrings($result);
    }

    public function toCoreCodebasePathCall(string $resolvedInclude, ?string $filePath = null): ?string
    {
        // Just scam this for the time being.
        $codeString = $this->toCodeString($resolvedInclude, $filePath);

        if (!str_starts_with($resolvedInclude, '@')) {
            return $codeString;
        }
        if (preg_match('#^@[/\\\]?$#', $resolvedInclude)) {
            return $codeString;
        }

        $result = $codeString;

        $result = str_replace('$CFG->admin', '\'admin\'', $result);
        $result = str_replace('$CFG->libdir', '$CFG->dirroot . \'/lib\'', $result);
        $result = $this->mergeStrings($result);

        if (str_starts_with($result, '$CFG->dirroot')) {
            $result = str_replace('$CFG->dirroot . ', '', $result);
            $result = '\core_codebase::path(' . $result . ')';
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * We can't simply use $includeParts = explode('/', $resolvedInclude);
     * The lookbehind is to prevent matching a slash as a parameter, specifically
     * in ltrim($observer['includefile'], '/') from \core\event\manager.
     *
     * @param string $resolvedInclude
     * @return array|false
     * @todo This is a bit shoddy. It would be better if the lookbehind matched everything but a closing brace,
     *       and there was a matching lookahead that matched everything but an opening one. (Update: we can't
     *       do it that way as negative lookbehinds have to be fixed length.)
     */
    private function splitResolvedInclude(string $resolvedInclude): array|false
    {
        return preg_split('#(?<![\'"])/(?!=[^\'"])#', $resolvedInclude);
    }

    /**
     * @param array|string $result
     * @return array|string|string[]
     */
    private function mergeStrings(array|string $result): string|array
    {
        return str_replace(self::QUOTE . ' . ' . self::QUOTE, '', $result);
    }

    /**
     * @param string $resolvedInclude
     * @return string[]
     */

}