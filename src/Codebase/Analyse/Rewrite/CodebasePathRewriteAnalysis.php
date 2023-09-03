<?php

namespace MoodleAnalyse\Codebase\Analyse\Rewrite;

use MoodleAnalyse\Codebase\CodebasePath;
use MoodleAnalyse\Rewrite\Rewrite;

class CodebasePathRewriteAnalysis
{

    private ?Rewrite $rewrite = null;


    /**
     * @var string|null The reason why a rewrite is required.
     */
    private ?string $explanation;

    private bool $worthInvestigating = false;

    public function __construct(private CodebasePath $codebasePath)
    {
    }

    public function getRewrite(): ?Rewrite
    {
        return $this->rewrite;
    }

    public function setRewrite(?Rewrite $rewrite): void
    {
        $this->rewrite = $rewrite;
        // Assume that if we have a rewrite it is sensible.
        $this->worthInvestigating = false;
    }

    public function isWorthInvestigating(): bool
    {
        return $this->worthInvestigating;
    }

    public function setWorthInvestigating(bool $worthInvestigating): void
    {
        $this->worthInvestigating = $worthInvestigating;
    }



    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): void
    {
        $this->explanation = $explanation;
    }

    public function getCodebasePath(): CodebasePath
    {
        return $this->codebasePath;
    }

}