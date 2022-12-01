<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

/**
 * @todo Fix the naming of this class, properties and getters.
 */
class PathCode
{
    private string $resolvedPath;
    private ?string $pathComponent;
    private ?string $pathWithinComponent;

    public function __construct(private string $pathCode, private int $pathCodeStartLine, private int $pathCodeEndLine, private int $pathCodeStartFilePos, private int $pathCodeEndFilePos)
    {
    }

    public function getPathCode(): string
    {
        return $this->pathCode;
    }

    public function getPathCodeStartLine(): int
    {
        return $this->pathCodeStartLine;
    }

    public function getPathCodeEndLine(): int
    {
        return $this->pathCodeEndLine;
    }

    public function getPathCodeStartFilePos(): int
    {
        return $this->pathCodeStartFilePos;
    }

    public function getPathCodeEndFilePos(): int
    {
        return $this->pathCodeEndFilePos;
    }

    public function getResolvedPath(): string
    {
        return $this->resolvedPath;
    }

    public function setResolvedPath(string $resolvedPath): void
    {
        $this->resolvedPath = $resolvedPath;
    }

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

    public function setPathComponent(?string $pathComponent): void
    {
        $this->pathComponent = $pathComponent;
    }

    public function getPathWithinComponent(): ?string
    {
        return $this->pathWithinComponent;
    }

    public function setPathWithinComponent(?string $pathWithinComponent): void
    {
        $this->pathWithinComponent = $pathWithinComponent;
    }




}