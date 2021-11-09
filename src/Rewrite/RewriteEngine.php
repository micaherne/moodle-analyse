<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite;

use Exception;
use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\Rewrite\Strategy\RewriteStrategy;
use MoodleAnalyse\Visitor\FileAwareInterface;
use PhpParser\Lexer;
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
class RewriteEngine
{

    private array $excludedFiles = [
        'install.php',
        'admin/cli/install.php',
        'admin/cli/install_database.php',
        'admin/tool/phpunit/cli/init.php',
        'admin/tool/phpunit/cli/util.php',
        'lib/ajax/service-nologin.php', // Just includes lib/ajax/service.php
        'lib/classes/component.php',
        'lib/setup.php',
        'lib/setuplib.php',
        'lib/phpunit/bootstrap.php',
        'lib/phpunit/bootstraplib.php',


        'config.php' // Shouldn't be there but let's exclude it in case it is.
    ];

    private array $visitors = [];

    /**
     * The traversers required to run the rewrite.
     * @var NodeTraverser[]
     */
    private array $traversers = [];

    private $rewriteLogDirectory;

    private $rewriteLogFiles = [];

    public function __construct(
        private string $moodleroot,
        private LoggerInterface $logger,
        private RewriteStrategy $strategy
    ) {
        $this->rewriteLogDirectory = $this->moodleroot . '/.rewrite';
    }

    /**
     * @throws Exception
     */
    public function rewrite()
    {
        if (!is_dir($this->moodleroot) || !file_exists($this->moodleroot . '/version.php')) {
            throw new Exception("$this->moodleroot is not a Moodle directory");
        }

        if (is_dir($this->rewriteLogDirectory)) {
            throw new Exception("Rewrite log directory already exists");
        }

        mkdir($this->rewriteLogDirectory);

        $finder = new FileFinder($this->moodleroot);

        $lexer = new Lexer(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

        $this->visitors = $this->strategy->getVisitors();

        foreach ($this->visitors as $run => $visitors) {
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            $traverser->addVisitor(new ParentConnectingVisitor());
            foreach ($visitors as $visitor) {
                $traverser->addVisitor($visitor);
            }
            $this->traversers[$run] = $traverser;
        }

        $start = time();

        /** @var SplFileInfo $file */
        foreach ($finder->getFileIterator() as $file) {

            $relativePathname = (string)str_replace('\\', '/', $file->getRelativePathname());

            // TODO: Should maybe get the list of excluded files from the strategy.
            if (in_array($relativePathname, $this->excludedFiles)) {
                $this->logger->info("Ignoring excluded file $relativePathname");
                continue;
            }

            $this->logger->info("Rewriting $file");

            $contents = $file->getContents();
            try {
                $nodes = $parser->parse($contents);
            } catch (Exception $e) {
                $this->rewriteLog('failed-files', $file, [$e->getMessage()]);
            }

            foreach ($this->traversers as $run => $traverser) {
                foreach ($this->visitors[$run] as $visitor) {
                    if ($visitor instanceof FileAwareInterface) {
                        $visitor->setFile($file);
                    }
                }
                $nodes = $traverser->traverse($nodes);
            }

            $rewrites = $this->strategy->getRewrites($nodes, $contents, $relativePathname);

            if (count($rewrites) === 0) {
                // TODO: Log no rewrites.
                continue;
            }

            // Order nodes in reverse so we don't overwrite rewrites.
            usort(
                $rewrites,
                fn(Rewrite $rewrite1, Rewrite $rewrite2) => $rewrite2->getStartPos() - $rewrite1->getStartPos()
            );

            $this->logger->debug("Rewriting {$relativePathname}");

            $rewritten = $contents;
            foreach ($rewrites as $rewrite) {
                $this->logger->debug(
                    "Rewriting " . substr(
                        $rewritten,
                        $rewrite->getStartPos(),
                        $rewrite->getLength()
                    ) . ' to ' . $rewrite->getCode()
                );
                $rewritten = substr_replace($rewritten, $rewrite->getCode(), $rewrite->getStartPos(), $rewrite->getLength());
            }

            file_put_contents($file->getPathname(), $rewritten);

            // Log what's been done.
            foreach ($this->strategy->getCurrentFileLogData() as $logType => $items) {
                foreach ($items as $item) {
                    $this->rewriteLog($logType, $file, $item);
                }
            }

        }

        foreach ($this->rewriteLogFiles as $logFile) {
            fclose($logFile);
        }

        $timeTaken = time() - $start;
        $this->logger->notice("Finished rewriting {$this->moodleroot} in $timeTaken seconds");
    }

    private function rewriteLog(string $logType, SplFileInfo $file, array $logData = []): void
    {
        if (!array_key_exists($logType, $this->rewriteLogFiles)) {
            $this->rewriteLogFiles[$logType] = fopen($this->rewriteLogDirectory . '/' . $logType . '.csv', 'w');
        }
        fputcsv($this->rewriteLogFiles[$logType], [$file->getRelativePathname(), ...$logData]);
    }


}