<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase\Analyse;

use Exception;
use MoodleAnalyse\Codebase\CodebasePath;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\PathCode;
use MoodleAnalyse\Codebase\ResolvedPathProcessor;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class FileAnalyser
{

    private Parser $parser;
    private PathResolvingVisitor $pathResolvingVisitor;
    private ResolvedPathProcessor $resolvedPathProcessor;
    private NodeTraverser $preProcessTraverser;
    private NodeTraverser $processTraverser;

    public function __construct(private readonly ComponentResolver $componentResolver)
    {
        $lexer = new Lexer(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

        $this->preProcessTraverser = new NodeTraverser();
        $this->preProcessTraverser->addVisitor(new NameResolver());
        $this->preProcessTraverser->addVisitor(new ParentConnectingVisitor());
        $this->preProcessTraverser->addVisitor(new PathFindingVisitor());

        $this->processTraverser = new NodeTraverser();
        $this->processTraverser->addVisitor(new NameResolver());
        $this->processTraverser->addVisitor(new ParentConnectingVisitor());
        $this->pathResolvingVisitor = new PathResolvingVisitor();
        $this->processTraverser->addVisitor($this->pathResolvingVisitor);

        $this->resolvedPathProcessor = new ResolvedPathProcessor();
    }

    /**
     * @throws Exception
     */
    public function analyseFile(SplFileInfo $finderFile): FileAnalysis
    {
        $relativePathname = str_replace('\\', '/', $finderFile->getRelativePathname());

        $sourceComponent = $this->componentResolver->resolveComponent($relativePathname);
        if (is_null($sourceComponent)) {
            throw new \RuntimeException("Unable to resolve component for known path");
        }

        $sourceComponentName = $sourceComponent[0] . '_' . ($sourceComponent[1] ?? 'lib');

        $fileAnalysis = new FileAnalysis($finderFile, $sourceComponentName);

        $this->pathResolvingVisitor->setFilePath($relativePathname);

        $contents = $finderFile->getContents();

        $nodes = $this->parser->parse($contents);
        if (is_null($nodes)) {
            throw new RuntimeException("Unable to parse file $relativePathname");
        }
        $nodes = $this->preProcessTraverser->traverse($nodes);

        $this->processTraverser->traverse($nodes);

        $pathNodes = $this->pathResolvingVisitor->getPathNodes();
        foreach ($pathNodes as $pathNode) {

            // Check for config.php includes (i.e. the file is some kind of entry point).
            if ($pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE ) == '@/config.php'
                && $pathNode->getAttribute('parent')?->getAttribute(PathResolvingVisitor::IS_CONFIG_INCLUDE)) {
                $fileAnalysis->setIncludesConfig(true);
            }

            $code = substr(
                $contents,
                $pathNode->getStartFilePos(),
                $pathNode->getEndFilePos() - $pathNode->getStartFilePos() + 1
            );

            $pathCode = new PathCode(
                $code,
                $pathNode->getStartLine(),
                $pathNode->getEndLine(),
                $pathNode->getStartFilePos(),
                $pathNode->getEndFilePos()
            );

            $resolvedInclude = $pathNode->getAttribute('resolvedInclude');

            $pathCode->setResolvedPath($resolvedInclude);


            $targetComponent = $this->componentResolver->resolveComponent($resolvedInclude);
            $targetComponentName = null;
            $pathWithinComponent = null;
            if (!is_null($targetComponent)) {
                // ComponentResolver returns null for lib (as Moodle does) so change it for readability.
                $targetComponentName = $targetComponent[0] . '_' . ($targetComponent[1] ?? 'lib');
                $pathWithinComponent = $targetComponent[2];
            }

            $pathCode->setPathComponent($targetComponentName);
            $pathCode->setPathWithinComponent($pathWithinComponent);

            $parentPathCode = null;
            if ($pathNode->hasAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION)) {
                /** @var Expr $parentNode */
                $parentNode = $pathNode->getAttribute(PathResolvingVisitor::CONTAINING_EXPRESSION);
                $parentCode = substr(
                    $contents,
                    $parentNode->getStartFilePos(),
                    $parentNode->getEndFilePos() - $parentNode->getStartFilePos() + 1
                );

                $parentPathCode = new PathCode(
                    $parentCode,
                    $parentNode->getStartLine(),
                    $parentNode->getEndLine(),
                    $parentNode->getStartFilePos(),
                    $parentNode->getEndFilePos()
                );

                $parentPathCode->setPathComponent(null);
                $parentPathCode->setPathWithinComponent(null);

                // Don't hold a reference to the node.
                unset($parentNode);
            }

            $category = $this->resolvedPathProcessor->categorise($resolvedInclude);

            $fromCoreComponent = !is_null($pathNode->getAttribute(PathResolvingVisitor::FROM_CORE_COMPONENT));
            $assignedFromPathVar = !is_null(
                $pathNode->getAttribute(PathResolvingVisitor::ASSIGNED_FROM_PATH_VAR)
            );

            $codebasePath = new CodebasePath($relativePathname, $sourceComponentName, $pathCode, $parentPathCode);
            $codebasePath->setPathCategory($category);
            $codebasePath->setFromCoreComponent($fromCoreComponent);
            $codebasePath->setAssignedFromPreviousPathVariable($assignedFromPathVar);

            $fileAnalysis->addCodebasePath($codebasePath);
        }

        return $fileAnalysis;
    }
}