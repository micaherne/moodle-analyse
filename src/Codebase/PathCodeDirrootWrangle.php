<?php

namespace MoodleAnalyse\Codebase;

class PathCodeDirrootWrangle extends PathCode
{
    public function __construct(
        string $pathCode,
        int $pathCodeStartLine,
        int $pathCodeEndLine,
        int $pathCodeStartFilePos,
        int $pathCodeEndFilePos,
        private int $classification,
        private ?string $variableName,
        private ?string $other, // e.g. the string to replace with
    ) {
        parent::__construct(
            $pathCode,
            $pathCodeStartLine,
            $pathCodeEndLine,
            $pathCodeStartFilePos,
            $pathCodeEndFilePos
        );
    }

    /**
     * @return int
     */
    public function getClassification(): int
    {
        return $this->classification;
    }

    /**
     * @return string|null
     */
    public function getVariableName(): ?string
    {
        return $this->variableName;
    }

    /**
     * @return string|null
     */
    public function getOther(): ?string
    {
        return $this->other;
    }



}