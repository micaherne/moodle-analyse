<?php

namespace MoodleAnalyse\Codebase;

/**
 * A special path code class for dirroot wrangling. The standard PathCode does not include enough information
 * about what the wrangle is doing.
 */
class PathCodeDirrootWrangle extends PathCode
{
    /**
     * @param string $pathCode
     * @param int $pathCodeStartLine
     * @param int $pathCodeEndLine
     * @param int $pathCodeStartFilePos
     * @param int $pathCodeEndFilePos
     * @param int $classification the classification of the wrangle {@see DirrootAnalyser}
     * @param string|null $variableName the variable name for the wrangle (e.g. for making relative)
     * @param string|null $replacementString the replacement string where it's a string replacement
     * @param bool|null $allowRelativePaths whether to allow paths that are already relative (when converting a path to relative)
     */
    public function __construct(
        string $pathCode,
        int $pathCodeStartLine,
        int $pathCodeEndLine,
        int $pathCodeStartFilePos,
        int $pathCodeEndFilePos,
        private int $classification,
        private ?string $variableName,
        private ?string $replacementString,

        private ?bool $allowRelativePaths = false
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
    public function getReplacementString(): ?string
    {
        return $this->replacementString;
    }

    public function getAllowRelativePaths(): ?bool
    {
        return $this->allowRelativePaths;
    }

}