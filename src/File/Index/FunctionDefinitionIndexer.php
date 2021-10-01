<?php

namespace MoodleAnalyse\File\Index;

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\FindingVisitor;
use Symfony\Component\Finder\SplFileInfo;

class FunctionDefinitionIndexer implements FileIndexer
{

    private SplFileInfo $file;

    /**
     * @var NodeVisitor[]
     */
    private array $visitors = [];

    private string $fileContents;

    /** @var FindingVisitor */
    private $functionFindingVisitor;

    /**
     * @return NodeVisitor[]
     */
    public function getNodeVisitors(): array
    {
        if (count($this->visitors) !== 0) {
            return $this->visitors;
        }

        $this->functionFindingVisitor = new FindingVisitor(fn(Node $node) => $node instanceof Function_);

        $this->visitors = [
            $this->functionFindingVisitor
        ];

        return $this->visitors;
    }

    public function setFile(SplFileInfo $file): void
    {
        $this->file = $file;
    }

    public function writeIndex(): void
    {
        /** @var Function_ $functionDefinition */
        foreach ($this->functionFindingVisitor->getFoundNodes() as $functionDefinition) {
            echo $this->file->getRelativePathname() . ': ' . $functionDefinition->name->name . "\n";
        }
    }

    public function setFileContents(string $fileContents): void
    {
        $this->fileContents = $fileContents;
    }

    /**
     * @return NodeVisitor[]
     */
}