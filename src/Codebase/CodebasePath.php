<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

class CodebasePath
{

    private ?PathCategory $pathCategory;
    private bool $fromCoreComponent = false;
    private bool $assignedFromPreviousPathVariable = false;

    /**
     * @param PathCode $parentCode
     */
    public function __construct(
        private string $relativeFilename,
        private string $fileComponent,
        private PathCode $pathCode,
        private ?PathCode $parentCode
    ) {

    }

    public function getRelativeFilename(): string
    {
        return $this->relativeFilename;
    }

    public function getFileComponent(): string
    {
        return $this->fileComponent;
    }

    public function getPathCode(): PathCode
    {
        return $this->pathCode;
    }

    public function getParentCode(): ?PathCode
    {
        return $this->parentCode;
    }

    public function getPathCategory(): ?PathCategory
    {
        return $this->pathCategory;
    }

    public function setPathCategory(?PathCategory $pathCategory): void
    {
        $this->pathCategory = $pathCategory;
    }

    public function isFromCoreComponent(): bool
    {
        return $this->fromCoreComponent;
    }

    public function setFromCoreComponent(bool $fromCoreComponent): void
    {
        $this->fromCoreComponent = $fromCoreComponent;
    }

    public function isAssignedFromPreviousPathVariable(): bool
    {
        return $this->assignedFromPreviousPathVariable;
    }

    public function setAssignedFromPreviousPathVariable(bool $assignedFromPreviousPathVariable): void
    {
        $this->assignedFromPreviousPathVariable = $assignedFromPreviousPathVariable;
    }




}