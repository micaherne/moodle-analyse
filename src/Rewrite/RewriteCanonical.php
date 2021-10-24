<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite;

use Exception;
use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Rewrite file paths to standard $CFG->dirroot etc format.
 *
 * @todo This does not work in some edge cases where $CFG is not actually available
 *       but hasn't been detected as such, e.g. lib/form/templatable_form_element.php where it's included
 *       inside a function that doesn't have a global $CFG statement.
 *
 * @todo Also doesn't work in tests with top level includes for the same reason, e.g.
 *       lib/dml/tests/dml_mysqli_read_slave_test.php
 */
class RewriteCanonical
{

    private array $excludedFiles = [
        'install.php',
        'admin/cli/install.php',
        'admin/cli/install_database.php',
        'admin/tool/phpunit/cli/init.php',
        'admin/tool/phpunit/cli/util.php',
        'lib/setup.php',
        'lib/setuplib.php',
        'lib/phpunit/bootstrap.php',
        'lib/phpunit/bootstraplib.php',
    ];

    public function __construct(
        private string $moodleroot,
        private LoggerInterface $logger,
        private $resolvedIncludeProcessor
    ) {
    }

    /**
     * @throws Exception
     */
    public function rewrite()
    {
        if (!is_dir($this->moodleroot) || !file_exists($this->moodleroot . '/version.php')) {
            throw new Exception("$this->moodleroot is not a Moodle directory");
        }

        $branchName = 'rewrite1-' . time();

        $this->logger->info("Branch name: $branchName");

        /*$process = new Process(['git', 'checkout', '-b', $branchName, 'master'], $this->moodleroot);
        $process->mustRun();*/

        $finder = new FileFinder($this->moodleroot);

        $lexer = new Lexer(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

        $preProcessTraverser = new NodeTraverser();
        $preProcessTraverser->addVisitor(new NameResolver());
        $preProcessTraverser->addVisitor(new ParentConnectingVisitor());
        $preProcessTraverser->addVisitor(new PathFindingVisitor());

        $processTraverser = new NodeTraverser();
        $processTraverser->addVisitor(new NameResolver());
        $processTraverser->addVisitor(new ParentConnectingVisitor());
        $pathResolvingVisitor = new PathResolvingVisitor();
        $processTraverser->addVisitor($pathResolvingVisitor);

        $start = time();

        /** @var SplFileInfo $file */
        foreach ($finder->getFileIterator() as $file) {
            $relativePathname = (string)str_replace('\\', '/', $file->getRelativePathname());

            if (in_array($relativePathname, $this->excludedFiles)) {
                $this->logger->info("Ignoring excluded file $relativePathname");
                continue;
            }

            $this->logger->info("Rewriting $file");

            $pathResolvingVisitor->setFilePath($relativePathname);
            $contents = $file->getContents();
            $nodes = $parser->parse($contents);
            $nodes = $preProcessTraverser->traverse($nodes);

            $processTraverser->traverse($nodes);
            $pathNodes = $pathResolvingVisitor->getPathNodes();

            // Order nodes in reverse so we don't overwrite rewrites.
            usort($pathNodes, fn(Node $node1, Node $node2) => $node2->getStartFilePos() - $node1->getStartFilePos());

            $rewrites = $this->getRewritesCanonical($pathNodes, $relativePathname);

            if (count($rewrites) === 0) {
                continue;
            }

            // Try to be nice to the garbage collector.
            unset($pathNodes);

            $this->logger->debug("Rewriting {$relativePathname}");

            $rewritten = $contents;
            foreach ($rewrites as $rewrite) {
                $length = $rewrite[1] - $rewrite[0] + 1;
                $this->logger->debug("Rewriting " . substr($rewritten, $rewrite[0], $length) . ' to ' . $rewrite[2]);
                $rewritten = substr_replace($rewritten, $rewrite[2], $rewrite[0], $length);
            }

            file_put_contents($file->getPathname(), $rewritten);
        }

        $timeTaken = time() - $start;
        $this->logger->notice("Finished rewriting branch $branchName in $timeTaken seconds");
    }

    /**
     * @param array $pathNodes
     * @param string $relativePathname
     * @return array
     */
    private function getRewritesCanonical(array $pathNodes, string $relativePathname): array
    {
        $rewrites = [];
        /** @var Node $pathNode */
        foreach ($pathNodes as $pathNode) {
            // Ensure $CFG is available.
            if (!$pathNode->getAttribute(PathResolvingVisitor::CFG_AVAILABLE)
                && ($pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE) !== '@/config.php')) {
                $this->logger->debug(
                    "Ignoring as \$CFG may be unavailable: {$relativePathname}: {$pathNode->getStartFilePos()}"
                );
                continue;
            }

            // If the path is part of a property definition we can't rewrite it to anything but a literal.
            if ($pathNode->getAttribute(PathFindingVisitor::ATTR_IN_PROPERTY_DEF)) {
                $this->logger->info("Ignoring as path is in a property definition");
                continue;
            }

            $resolvedInclude = $pathNode->getAttribute('resolvedInclude');
            $category = $this->resolvedIncludeProcessor->categorise($resolvedInclude);

            if (!is_null($category) && str_starts_with($category, 'suspect')) {
                $this->logger->debug("Ignoring suspect rewrite {$relativePathname}: {$pathNode->getStartFilePos()}");
                continue;
            }
            $rewrites[] = [
                $pathNode->getStartFilePos(),
                $pathNode->getEndFilePos(),
                $this->resolvedIncludeProcessor->toCodeString($resolvedInclude, $relativePathname)
            ];
        }
        return $rewrites;
    }
}