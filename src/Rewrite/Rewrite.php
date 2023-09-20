<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite;

/**
 * A rewrite of a piece of code.
 */
class Rewrite
{
    public function __construct(private int $startPos, private int $endPos, private string $code)
    {
    }

    /**
     * @return int
     */
    public function getStartPos(): int
    {
        return $this->startPos;
    }

    /**
     * @return int
     */
    public function getEndPos(): int
    {
        return $this->endPos;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    public function getLength(): int {
        return $this->endPos - $this->startPos + 1;
    }

    /**
     * Given a part of a resolved path, return some valid PHP code for it.
     *
     * This is required as we are extracting things like function calls and class constants which
     * cannot be interpolated in a string.
     *
     * @param string $string
     * @return string
     */
    protected function toCodeString(string $string): string
    {
        $default = '"' . $string . '"';
        if (!str_contains($string, '{')) {
            return $default;
        }
        $matches = [];
        $hasNonInterpolableItems = preg_match_all('#{(?!\$)(.+?)}#', $string, $matches, PREG_OFFSET_CAPTURE);
        if (!$hasNonInterpolableItems) {
            return $default;
        }

        $resultParts = [];
        $index = 0;
        for ($i = 0; $i < count($matches[0]); $i++) {
            $resultParts[] = '"' . substr($string, $index, $matches[0][$i][1] - $index) . '"';
            $resultParts[] = $matches[1][$i][0];
            $index += $matches[0][$i][1] + strlen($matches[0][$i][0]);
        }

        if ($index < strlen($string)) {
            $resultParts[] = '"' . substr($string, $index) . '"';
        }


        return implode(' . ', $resultParts);
    }


}