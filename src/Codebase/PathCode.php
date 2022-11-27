<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

/**
 * @todo Fix the naming of this class, properties and getters.
 */
class PathCode
{
    private string $pathCode;
    private int $pathCodeStartLine;
    private int $pathCodeEndLine;
    private int $pathCodeStartFilePos;
    private int $pathCodeEndFilePos;
    private string $resolvedPath;
    private ?string $pathComponent;
    private ?string $pathWithinComponent;

    /**
     * @param string $pathCode
     * @param int $pathCodeStartLine
     * @param int $pathCodeEndLine
     * @param int $pathCodeStartFilePos
     * @param int $pathCodeEndFilePos
     */
    public function __construct(
        string $pathCode,
        int $pathCodeStartLine,
        int $pathCodeEndLine,
        int $pathCodeStartFilePos,
        int $pathCodeEndFilePos
    ) {
        $this->pathCode = $pathCode;
        $this->pathCodeStartLine = $pathCodeStartLine;
        $this->pathCodeEndLine = $pathCodeEndLine;
        $this->pathCodeStartFilePos = $pathCodeStartFilePos;
        $this->pathCodeEndFilePos = $pathCodeEndFilePos;
    }

    /**
     * @return string
     */
    public function getPathCode(): string
    {
        return $this->pathCode;
    }

    /**
     * @return int
     */
    public function getPathCodeStartLine(): int
    {
        return $this->pathCodeStartLine;
    }

    /**
     * @return int
     */
    public function getPathCodeEndLine(): int
    {
        return $this->pathCodeEndLine;
    }

    /**
     * @return int
     */
    public function getPathCodeStartFilePos(): int
    {
        return $this->pathCodeStartFilePos;
    }

    /**
     * @return int
     */
    public function getPathCodeEndFilePos(): int
    {
        return $this->pathCodeEndFilePos;
    }

    /**
     * @return string
     */
    public function getResolvedPath(): string
    {
        return $this->resolvedPath;
    }

    /**
     * @param string $resolvedPath
     */
    public function setResolvedPath(string $resolvedPath): void
    {
        $this->resolvedPath = $resolvedPath;
    }

    /**
     * @return string|null
     */
    public function getPathComponent(): ?string
    {
        $pathComponent = $this->pathComponent;
        // We have a couple of component names returned by ComponentResolver that are not known by Moodle, so we
        // rewrite these here.
        if ($pathComponent === 'core_lib') {
            $pathComponent = 'core';
        } elseif ($pathComponent === 'core_root') {
            $pathComponent = null;
        }
        return $pathComponent;
    }

    /**
     * @param string|null $pathComponent
     */
    public function setPathComponent(?string $pathComponent): void
    {
        $this->pathComponent = $pathComponent;
    }

    /**
     * @return string|null
     */
    public function getPathWithinComponent(): ?string
    {
        return $this->pathWithinComponent;
    }

    /**
     * @param string|null $pathWithinComponent
     */
    public function setPathWithinComponent(?string $pathWithinComponent): void
    {
        $this->pathWithinComponent = $pathWithinComponent;
    }




}