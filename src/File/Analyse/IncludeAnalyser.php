<?php

namespace MoodleAnalyse\File\Analyse;

use JetBrains\PhpStorm\Pure;
use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\File\Index\BasicObjectIndex;
use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\FindingVisitor;

class IncludeAnalyser implements FileAnalyser, UsesComponentIdentifier
{

    /**
     * @var NodeVisitor[]
     */
    private array $visitors = [];

    private FindingVisitor $includeFindingVisitor;

    private IncludeResolvingVisitor $includeResolvingVisitor;

    private ComponentIdentifier $componentIdentifier;

    private FileDetails $fileDetails;

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


    /**
     * @throws \Exception
     */
    public function getAnalysis(): array
    {
        $index = [];
        /** @var Include_ $include */
        foreach ($this->includeFindingVisitor->getFoundNodes() as $include) {
            $parent = $include->getAttribute('parent')->getAttribute('parent');
            $resolvedInclude = $include->getAttribute(IncludeResolvingVisitor::RESOLVED_INCLUDE);
            $indexEntry = [
                'file' => str_replace('\\', '/', $this->fileDetails->getFileInfo()->getRelativePathname()),
                'fullIncludeText' => substr($this->fileDetails->getContents(), $include->getStartFilePos(), $include->getEndFilePos() - $include->getStartFilePos() + 1),
                'includeExpressionText' => substr($this->fileDetails->getContents(), $include->expr->getStartFilePos(), $include->expr->getEndFilePos() - $include->expr->getStartFilePos() + 1),
                'topLevel' => is_null($parent),
                'resolved' => $resolvedInclude,
                'includeComponent' => $this->componentIdentifier->fileComponent(substr($resolvedInclude, 2)),
                'includePosition' => [$include->getStartFilePos(), $include->getEndFilePos()],
                'includeExpressionPosition' => [$include->expr->getStartFilePos(), $include->expr->getEndFilePos()],
            ];
            $indexEntry['key'] = sha1($indexEntry['file'] . ':' . $indexEntry['fullIncludeText'] . ':' . $indexEntry['includePosition'][0]);
            $index[$indexEntry['key']] = (object) $indexEntry;
        }

        return $index;
    }

    /**
     * @return BasicObjectIndex[]
     */
    #[Pure] public function getIndexes(): array {
        return [
            new BasicObjectIndex('include',
                ['file', 'fullIncludeText', 'includeExpressionText', 'resolved', 'includeComponent'],
                [self::class])
        ];
    }

    public function setComponentIdentifier(ComponentIdentifier $componentIdentifier): void
    {
        $this->componentIdentifier = $componentIdentifier;
    }

    public function setFileDetails(FileDetails $fileDetails): void
    {
        $this->fileDetails = $fileDetails;
        $this->includeResolvingVisitor->setFile($fileDetails->getFileInfo());
    }
}