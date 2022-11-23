<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

class CodebasePath
{

    private ?PathCategory $pathCategory;
    private bool $fromCoreComponent = false;
    private bool $assignedFromPreviousPathVariable = false;

    /**
     * @param string $relativeFilename
     * @param string $fileComponent
     * @param PathCode $pathCode
     * @param PathCode $parentCode
     */
    public function __construct(
        private string $relativeFilename,
        private string $fileComponent,
        private PathCode $pathCode,
        private ?PathCode $parentCode
    ) {

    }

    /**
     * @return string
     */
    public function getRelativeFilename(): string
    {
        return $this->relativeFilename;
    }

    /**
     * @return string
     */
    public function getFileComponent(): string
    {
        return $this->fileComponent;
    }

    /**
     * @return PathCode
     */
    public function getPathCode(): PathCode
    {
        return $this->pathCode;
    }

    /**
     * @return PathCode|null
     */
    public function getParentCode(): ?PathCode
    {
        return $this->parentCode;
    }

    /**
     * @return PathCategory|null
     */
    public function getPathCategory(): ?PathCategory
    {
        return $this->pathCategory;
    }

    /**
     * @param PathCategory|null $pathCategory
     */
    public function setPathCategory(?PathCategory $pathCategory): void
    {
        $this->pathCategory = $pathCategory;
    }

    /**
     * @return bool
     */
    public function isFromCoreComponent(): bool
    {
        return $this->fromCoreComponent;
    }

    /**
     * @param bool $fromCoreComponent
     */
    public function setFromCoreComponent(bool $fromCoreComponent): void
    {
        $this->fromCoreComponent = $fromCoreComponent;
    }

    /**
     * @return bool
     */
    public function isAssignedFromPreviousPathVariable(): bool
    {
        return $this->assignedFromPreviousPathVariable;
    }

    /**
     * @param bool $assignedFromPreviousPathVariable
     */
    public function setAssignedFromPreviousPathVariable(bool $assignedFromPreviousPathVariable): void
    {
        $this->assignedFromPreviousPathVariable = $assignedFromPreviousPathVariable;
    }




}