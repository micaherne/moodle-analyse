<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

/**
 * A representation of a piece of code that contains a path within the Moodle codebase.
 *
 * The line and file position properties are within the context of a file but the file is not stored here.
 * These would normally be used inside a FileAnalysis object.
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
        return $this->pathComponent;
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