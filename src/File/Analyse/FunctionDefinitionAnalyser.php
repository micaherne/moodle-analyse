<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\File\Index\BasicObjectIndex;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\FindingVisitor;

class FunctionDefinitionAnalyser implements FileAnalyser
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

    public function getAnalysis(): array
    {
        $result = [];

        /** @var Function_ $functionDef */
        foreach ($this->functionFindingVisitor->getFoundNodes() as $functionDef) {
            $indexEntry = [
                'file' => $this->fileDetails->getFileInfo()->getRelativePathname(),
                'functionName' => $functionDef->namespacedName->toCodeString(),
                'filePosition' => [$functionDef->getStartFilePos(), $functionDef->getEndFilePos()]
            ];
            $indexEntry['key'] = sha1($indexEntry['file'] . ':' . $indexEntry['functionName'] . ':' . $indexEntry['filePosition'][0]);
            $result[] = (object) $indexEntry;
        }
        return $result;
    }

    public function setFileDetails(FileDetails $fileDetails): void
    {
        $this->fileDetails = $fileDetails;
    }

    public function getIndexes(): array
    {
        return [
            new BasicObjectIndex('functionDef', ['file'], [self::class])
        ];
    }
}