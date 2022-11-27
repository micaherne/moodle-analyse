<?php

namespace MoodleAnalyse\Codebase\Rewrite\Rewriter;

use MoodleAnalyse\Rewrite\Rewrite;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\SplFileInfo;

class AbstractRewriter
{
    public function __construct(protected readonly LoggerInterface $logger = new NullLogger())
    {
    }

    /**
     * Take a list of rewrites and apply them to the file.
     *
     * @param array<Rewrite> $rewrites
     * @param SplFileInfo $finderFile
     * @return void
     */
    protected function applyRewrites(array $rewrites, SplFileInfo $finderFile): void
    {
        // Order nodes in reverse so we don't overwrite rewrites.
        usort(
            $rewrites,
            fn(Rewrite $rewrite1, Rewrite $rewrite2) => $rewrite2->getStartPos() - $rewrite1->getStartPos()
        );

        $this->logger->info("Rewriting " . $finderFile->getRelativePathname());
        $rewritten = $finderFile->getContents();
        foreach ($rewrites as $rewrite) {
            $this->logger->debug(
                "Rewriting " . substr(
                    $rewritten,
                    $rewrite->getStartPos(),
                    $rewrite->getLength()
                ) . ' to ' . $rewrite->getCode()
            );
            $rewritten = substr_replace(
                $rewritten,
                $rewrite->getCode(),
                $rewrite->getStartPos(),
                $rewrite->getLength()
            );
        }

        file_put_contents($finderFile->getRealPath(), $rewritten);

    }
}