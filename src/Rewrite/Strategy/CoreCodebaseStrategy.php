<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite\Strategy;

use MoodleAnalyse\Codebase\ResolvedIncludeProcessor;
use MoodleAnalyse\Rewrite\Rewrite;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\NodeVisitor;
use Psr\Log\LoggerInterface;

class CoreCodebaseStrategy implements RewriteStrategy
{

    private PathFindingVisitor $pathFindingVisitor;
    private PathResolvingVisitor $pathResolvingVisitor;
    /**
     * @var array[]
     */
    private array $currentFileLogData;

    private const CODEBASE_INCLUDE_FILES = [
        'install.php',
        'lib/setup.php',
        'admin/cli/install.php',
        'admin/cli/install_database.php'
    ];

    private const NO_REWRITE_FILES = [
        'install.php',
        'admin/cli/install.php',
        'admin/cli/install_database.php',
        'admin/tool/phpunit/cli/init.php',
        'admin/tool/phpunit/cli/util.php',
        'lib/ajax/service-nologin.php', // Just includes lib/ajax/service.php
        'lib/classes/component.php',
        'lib/setup.php',
        'lib/setuplib.php',
        'lib/phpunit/bootstrap.php',
        'lib/phpunit/bootstraplib.php',

        'config.php' // Shouldn't be there but let's exclude it in case it is.
    ];

    public function __construct(
        private LoggerInterface $logger,
        private ResolvedIncludeProcessor $resolvedIncludeProcessor
    ) {
        $this->pathResolvingVisitor = new PathResolvingVisitor();
        $this->pathFindingVisitor = new PathFindingVisitor();
    }


    /**
     * @return NodeVisitor[][]
     */
    public function getVisitors(): array
    {
        return [[$this->pathFindingVisitor], [$this->pathResolvingVisitor]];
    }

    /**
     * Get rewrites (array of start char no, end char no, code).
     *
     * @return Rewrite[]
     */
    public function getRewrites(array $nodes, string $fileContents, string $relativeFilePath): array
    {
        $pathNodes = $this->pathResolvingVisitor->getPathNodes();

        $rewrites = [];

        if (in_array($relativeFilePath, self::CODEBASE_INCLUDE_FILES)) {
            $rewrites[] = $this->getCodebaseIncludeRewrite($nodes, $fileContents, $relativeFilePath);
        }

        if (in_array($relativeFilePath, self::NO_REWRITE_FILES)) {
            return $rewrites;
        }

        $this->currentFileLogData = [
            'ignored' => [],
            'rewritten' => []
        ];

        foreach ($pathNodes as $pathNode) {
            $code = substr(
                $fileContents,
                $pathNode->getStartFilePos(),
                ($pathNode->getEndFilePos() - $pathNode->getStartFilePos()) + 1
            );

            // Ensure $CFG is available.
            /*if (!$pathNode->getAttribute(PathResolvingVisitor::CFG_AVAILABLE)
                && ($pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE) !== '@/config.php')) {
                $this->logger->debug(
                    "Ignoring as \$CFG may be unavailable: {$relativeFilePath}: {$pathNode->getStartFilePos()}"
                );
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, '$CFG may be unavailable'];
                continue;
            }*/

            // If the path is part of a property definition we can't rewrite it to anything but a literal.
            // TODO: We should check whether the component of the file is the same as the component of the path
            //  to see whether this is a problem or not (e.g. \tool_behat_manager_util_testcase::$corefeatures)
            if ($pathNode->getAttribute(PathFindingVisitor::ATTR_IN_PROPERTY_DEF)) {
                $this->logger->info("Ignoring as path is in a property definition");
                $this->currentFileLogData['ignored'][] = [
                    $pathNode->getStartLine(),
                    $code,
                    'Inside property definition'
                ];
                continue;
            }

            $resolvedInclude = $pathNode->getAttribute('resolvedInclude');
            // $category = $this->resolvedIncludeProcessor->categorise($resolvedInclude);

            // Check for dodgy ones where it was e.g. $CFG->dirroot in the middle of an error message string.
            $internalRoot = strpos(substr($resolvedInclude, 1), '@');
            if ($internalRoot !== false) {
                $this->logger->debug(
                    "Ignoring suspect rewrite {$relativeFilePath}: {$pathNode->getStartFilePos()}"
                );
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, 'Suspect resolved path'];
                continue;
            }

            // If it's just dirroot with or without a slash, it's some kind of dirroot wrangling probably.
            if ($resolvedInclude === '@' || $resolvedInclude === '@/' || $resolvedInclude === '@\\'
                || $resolvedInclude === '@{DIRECTORY_SEPARATOR}' || $resolvedInclude === '@{\\DIRECTORY_SEPARATOR}') {
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, 'Dirroot wrangling'];
                continue;
            }

            if ($resolvedInclude === '@/config.php') {
                $codeString = $this->resolvedIncludeProcessor->toCodeString(
                    $resolvedInclude,
                    $relativeFilePath
                );
            } else {
                $codeString = $this->resolvedIncludeProcessor->toCoreCodebaseCall(
                    $resolvedInclude,
                    $relativeFilePath
                );
            }

            if (is_null($codeString)) {
                $this->logger->info("Leaving include $resolvedInclude as-is");
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, 'No replacement given'];
                continue;
            }

            $rewrites[] = new Rewrite(
                $pathNode->getStartFilePos(),
                $pathNode->getEndFilePos(),
                $codeString
            );

            $this->currentFileLogData['rewritten'][] = [$pathNode->getStartLine(), $code, $codeString];
        }

        // Try to be nice to the garbage collector. We don't want any references to nodes left or we'll use up
        // too much memory.
        unset($pathNodes);

        return $rewrites;
    }



    /**
     * @return array[]
     */
    public function getCurrentFileLogData(): array
    {
        return $this->currentFileLogData;
    }


    public function addFiles(string $moodleroot): void
    {
        copy(__DIR__ . '/../../../resources/codebase.php', $moodleroot . '/lib/classes/codebase.php');
    }

    /**
     * Create a rewrite to add an include for core_codebase.
     *
     * @todo This isn't brilliant as it just bungs it in before the first node, which can confuse the comments,
     *       e.g. in lib/setup.php
     *
     * @param array $nodes
     * @param string $fileContents
     * @param string $relativeFilePath
     * @return Rewrite
     */
    private function getCodebaseIncludeRewrite(array $nodes, string $fileContents, string $relativeFilePath): Rewrite
    {
        $firstNode = $nodes[0];
        $code = substr(
            $fileContents,
            $firstNode->getStartFilePos(),
            ($firstNode->getEndFilePos() - $firstNode->getStartFilePos()) + 1
        );

        $require = 'require_once(__DIR__ . \''
            . str_repeat('/..', substr_count($relativeFilePath, '/'))
            . '/lib/classes/codebase.php\');';

        $rewrittenCode = $require . "\n\n" . $code;
        $this->currentFileLogData['rewritten'][] = [$firstNode->getStartLine(), $code, $rewrittenCode];

        return new Rewrite($firstNode->getStartFilePos(), $firstNode->getEndFilePos(), $rewrittenCode);
    }

}