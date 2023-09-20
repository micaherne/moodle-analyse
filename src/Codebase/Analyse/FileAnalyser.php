<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase\Analyse;

use Exception;
use MoodleAnalyse\Codebase\CodebasePath;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\DirrootAnalyser;
use MoodleAnalyse\Codebase\PathCode;
use MoodleAnalyse\Codebase\ResolvedPathProcessor;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use MoodleAnalyse\Visitor\Util;
use PhpParser\Lexer;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Analyses a given Moodle file using PHP-Parser.
 *
 * Returns a FileAnalysis object containing individual CodebasePath objects for each resolved path.
 */
class FileAnalyser
{

    private Parser $parser;
    private PathResolvingVisitor $pathResolvingVisitor;
    private ResolvedPathProcessor $resolvedPathProcessor;
    private NodeTraverser $preProcessTraverser;
    private NodeTraverser $processTraverser;
    private DirrootAnalyser $dirrootAnalyser;

    public function __construct(private readonly ComponentResolver $componentResolver)
    {
        $lexer = new Lexer(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

        // We need to run the path resolving visitor twice. This is because we need to identify the path nodes
        // before attempting to resolve the paths. The path resolving visitor needs to know in advance which
        // nodes are within paths so it can annotate them correctly.
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

        $this->dirrootAnalyser = new DirrootAnalyser();
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

        // First check if this is a CLI script.
        // We assume that the CLI_SCRIPT is a top-level node.
        foreach ($nodes as $node) {
            if (Util::isCliScriptDefine($node)) {
                $fileAnalysis->setIsCliScript(true);
                break;
            }
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

                // Check if the resolved include is a dirroot wrangle.
                $dirrootWrangle = null;
                if ($this->dirrootAnalyser->isDirroot($resolvedInclude)) {
                    $dirrootWrangle = $this->dirrootAnalyser->extractWrangle($pathNode, $contents);
                }

                if (!is_null($dirrootWrangle)) {
                    $parentPathCode = $dirrootWrangle;
                } else {
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
                }

                $parentPathCode->setPathComponent(null);
                $parentPathCode->setPathWithinComponent(null);

                // Don't hold a reference to the node.
                unset($parentNode);
            }

            $fromCoreComponent = !is_null($pathNode->getAttribute(PathResolvingVisitor::FROM_CORE_COMPONENT));
            $assignedFromPathVar = !is_null(
                $pathNode->getAttribute(PathResolvingVisitor::ASSIGNED_FROM_PATH_VAR)
            );

            $codebasePath = new CodebasePath($relativePathname, $sourceComponentName, $pathCode, $parentPathCode);
            $codebasePath->setFromCoreComponent($fromCoreComponent);
            $codebasePath->setAssignedFromPreviousPathVariable($assignedFromPathVar);

            $category = $this->resolvedPathProcessor->categoriseCodebasePath($codebasePath);
            $codebasePath->setPathCategory($category);

            $fileAnalysis->addCodebasePath($codebasePath);
        }

        return $fileAnalysis;
    }
}