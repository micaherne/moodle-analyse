<?php

namespace MoodleAnalyse\File\Index;

use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\FindingVisitor;
use PhpParser\NodeVisitorAbstract;
use Symfony\Component\Finder\SplFileInfo;

class IncludeIndexer implements FileIndexer
{
    private SplFileInfo $file;

    /**
     * @var NodeVisitor[]
     */
    private array $visitors = [];

    private string $fileContents;

    private FindingVisitor $includeFindingVisitor;

    private IncludeResolvingVisitor $includeResolvingVisitor;

    /**
     * @inheritDoc
     */
    public function getNodeVisitors(): array
    {
        if (count($this->visitors) !== 0) {
            return $this->visitors;
        }

        $this->includeFindingVisitor = new FindingVisitor(fn(Node $node) => $node instanceof Include_);

        $this->includeResolvingVisitor = new IncludeResolvingVisitor();

        $this->visitors = [
            $this->includeFindingVisitor,
            $this->includeResolvingVisitor,
        ];

        return $this->visitors;
    }

    public function setFile(SplFileInfo $file): void
    {
        $this->file = $file;
        // includeFindingVisitor doesn't need the file.
        $this->includeResolvingVisitor->setFile($file);
    }

    public function writeIndex(): void
    {
        /** @var Include_ $include */
        foreach ($this->includeFindingVisitor->getFoundNodes() as $include) {
            $parent = $include->getAttribute('parent')->getAttribute('parent');
            $indexEntry = [
                'file' => str_replace('\\', '/', $this->file->getRelativePathname()),
                'fullIncludeText' => substr($this->fileContents, $include->getStartFilePos(), $include->getEndFilePos() - $include->getStartFilePos() + 1),
                'includeExpressionText' => substr($this->fileContents, $include->expr->getStartFilePos(), $include->expr->getEndFilePos() - $include->expr->getStartFilePos() + 1),
                'topLevel' => is_null($parent),
                'resolved' => $include->getAttribute(IncludeResolvingVisitor::RESOLVED_INCLUDE),
                'includePosition' => [$include->getStartFilePos(), $include->getEndFilePos()],
                'includeExpressionPosition' => [$include->expr->getStartFilePos(), $include->expr->getEndFilePos()],
            ];
            $indexEntry['key'] = sha1($indexEntry['file'] . ':' . $indexEntry['fullIncludeText']);
            echo json_encode($indexEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    public function setFileContents(string $fileContents): void
    {
        $this->fileContents = $fileContents;
    }
}