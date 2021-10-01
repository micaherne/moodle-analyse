<?php

namespace MoodleAnalyse\File\Index;

use MoodleAnalyse\Codebase\ComponentIdentifier;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\FindingVisitor;
use Symfony\Component\Finder\SplFileInfo;

class FunctionDefinitionIndexer implements FileIndexer
{

    /**
     * @var NodeVisitor[]
     */
    private array $visitors = [];

    private FindingVisitor $functionFindingVisitor;

    private FileDetails $fileDetails;

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

    public function writeIndex(): void
    {
        /** @var Function_ $functionDefinition */
        foreach ($this->functionFindingVisitor->getFoundNodes() as $functionDefinition) {
            echo $this->fileDetails->getFileInfo()->getRelativePathname() . ': ' . $functionDefinition->name->name . "\n";
        }
    }

    public function setComponentIdentifier(ComponentIdentifier $componentIdentifier): void
    {
        $this->componentIdentifier = $componentIdentifier;
    }

    public function setFileDetails(FileDetails $fileDetails): void
    {
        $this->fileDetails = $fileDetails;
    }
}