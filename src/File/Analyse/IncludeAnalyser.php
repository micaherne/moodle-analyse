<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use Exception;
use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\File\Index\BasicObjectIndex;
use MoodleAnalyse\File\Index\CsvFileIndex;
use MoodleAnalyse\Visitor\IncludeResolvingVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\FindingVisitor;

class IncludeAnalyser implements FileAnalyser, UsesComponentResolver
{

    /**
     * @var NodeVisitor[]
     */
    private array $visitors = [];

    private FindingVisitor $includeFindingVisitor;

    private IncludeResolvingVisitor $includeResolvingVisitor;

    private ComponentResolver $componentResolver;

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
     * @throws Exception
     */
    public function getAnalysis(): array
    {
        $index = [];
        /** @var Include_ $include */
        foreach ($this->includeFindingVisitor->getFoundNodes() as $include) {
            $parent = $include->getAttribute('parent')->getAttribute('parent');
            $resolvedInclude = $include->getAttribute(IncludeResolvingVisitor::RESOLVED_INCLUDE);
            $resolvedComponent = $this->componentResolver->resolveComponent($resolvedInclude);
            $indexEntry = [
                'file' => str_replace('\\', '/', $this->fileDetails->getFileInfo()->getRelativePathname()),
                'fullIncludeText' => substr($this->fileDetails->getContents(), $include->getStartFilePos(), $include->getEndFilePos() - $include->getStartFilePos() + 1),
                'includeExpressionText' => substr($this->fileDetails->getContents(), $include->expr->getStartFilePos(), $include->expr->getEndFilePos() - $include->expr->getStartFilePos() + 1),
                'topLevel' => is_null($parent),
                'resolved' => $resolvedInclude,
                // 'includeComponent' => $this->componentResolver->fileComponent(substr($resolvedInclude, 2)),
                'includeResolvedComponent' => $resolvedComponent,
                'includeStart' => $include->getStartFilePos(),
                'includeEnd' => $include->getEndFilePos(),
                'includeExpressionStart' => $include->expr->getStartFilePos(),
                'includeExpressionEnd' => $include->expr->getEndFilePos(),
                'includePosition' => [$include->getStartFilePos(), $include->getEndFilePos()],
                'includeExpressionPosition' => [$include->expr->getStartFilePos(), $include->expr->getEndFilePos()],
            ];
            if (is_null($resolvedComponent)) {
                $indexEntry['includePathWithinComponent'] = 'Unresolved';
                $indexEntry['includeComponent'] = 'Unresolved';
            } else {
                $indexEntry['includePathWithinComponent'] = array_pop($resolvedComponent);
                if ($resolvedComponent[0] == 'core' && is_null($resolvedComponent[1])) {
                    $resolvedComponent[1] = 'lib';
                }
                $indexEntry['includeComponent'] = implode('_', $resolvedComponent);
            }

            $indexEntry['key'] = sha1($indexEntry['file'] . ':' . $indexEntry['fullIncludeText'] . ':' . $indexEntry['includePosition'][0]);
            $index[$indexEntry['key']] = (object) $indexEntry;
        }

        return $index;
    }

    /**
     * @return BasicObjectIndex[]
     */
    public function getIndexes(): array {
        return [
            new CsvFileIndex('include',
                ['file', 'fullIncludeText', 'includeExpressionText', 'resolved', 'includeComponent', 'includePathWithinComponent', 'includeStart', 'includeEnd', 'includeExpressionStart', 'includeExpressionEnd'],
                [self::class])
        ];
    }

    public function setComponentResolver(ComponentResolver $componentResolver): void
    {
        $this->componentResolver = $componentResolver;
    }

    public function setFileDetails(FileDetails $fileDetails): void
    {
        $this->fileDetails = $fileDetails;
        $this->includeResolvingVisitor->setFile($fileDetails->getFileInfo());
    }
}