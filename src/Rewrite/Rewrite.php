<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite;

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


}