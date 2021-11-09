<?php

declare(strict_types=1);

namespace MoodleAnalyse\Rewrite\Strategy;

use MoodleAnalyse\Codebase\ResolvedIncludeProcessor;
use MoodleAnalyse\Rewrite\Rewrite;
use MoodleAnalyse\Visitor\PathFindingVisitor;
use MoodleAnalyse\Visitor\PathResolvingVisitor;
use PhpParser\Node;
use Psr\Log\LoggerInterface;

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
class CanonicalStrategy implements RewriteStrategy
{

    private PathResolvingVisitor $pathResolvingVisitor;
    private PathFindingVisitor $pathFindingVisitor;
    /**
     * @var array[]
     */
    private array $currentFileLogData;

    public function __construct(
        private LoggerInterface $logger,
        private ResolvedIncludeProcessor $resolvedIncludeProcessor
    ) {
        $this->pathResolvingVisitor = new PathResolvingVisitor();
        $this->pathFindingVisitor = new PathFindingVisitor();
    }
    /**
     * @inheritDoc
     */
    public function getExcludedFiles(): array
    {
        return [
            'install.php',
            'admin/cli/install.php',
            'admin/cli/install_database.php',
            'admin/tool/phpunit/cli/init.php',
            'admin/tool/phpunit/cli/util.php',
            'lib/setup.php',
            'lib/setuplib.php',
            'lib/phpunit/bootstrap.php',
            'lib/phpunit/bootstraplib.php',

            'config.php' // Shouldn't be there but let's exclude it in case it is.
        ];
    }

    /**
     * @inheritDoc
     */
    public function getVisitors(): array
    {
        return [[$this->pathFindingVisitor], [$this->pathResolvingVisitor]];
    }

    /**
     * @inheritDoc
     */
    public function getRewrites(array $nodes, string $fileContents, string $relativeFilePath): array
    {
        $pathNodes = $this->pathResolvingVisitor->getPathNodes();

        $this->currentFileLogData = [
            'ignored' => [],
            'rewritten' => []
        ];

        $rewrites = [];

        foreach ($pathNodes as $pathNode) {

            $code = substr(
                $fileContents,
                $pathNode->getStartFilePos(),
                ($pathNode->getEndFilePos() - $pathNode->getStartFilePos()) + 1
            );

            // Ensure $CFG is available.
            if (!$pathNode->getAttribute(PathResolvingVisitor::CFG_AVAILABLE)
                && ($pathNode->getAttribute(PathResolvingVisitor::RESOLVED_INCLUDE) !== '@/config.php')) {
                $this->logger->debug(
                    "Ignoring as \$CFG may be unavailable: {$relativeFilePath}: {$pathNode->getStartFilePos()}"
                );
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, '$CFG may be unavailable'];
                continue;
            }

            // If the path is part of a property definition we can't rewrite it to anything but a literal.
            if ($pathNode->getAttribute(PathFindingVisitor::ATTR_IN_PROPERTY_DEF)) {
                $this->logger->info("Ignoring as path is in a property definition");
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, 'Inside property definition'];
                continue;
            }

            $resolvedInclude = $pathNode->getAttribute('resolvedInclude');
            $category = $this->resolvedIncludeProcessor->categorise($resolvedInclude);

            if (!is_null($category) && str_starts_with($category, 'suspect')) {
                $this->logger->debug("Ignoring suspect rewrite {$relativeFilePath}: {$pathNode->getStartFilePos()}");
                $this->currentFileLogData['ignored'][] = [$pathNode->getStartLine(), $code, 'Suspect resolved path'];
                continue;
            }
            $rewrites[] = new Rewrite(
                $pathNode->getStartFilePos(),
                $pathNode->getEndFilePos(),
                $this->resolvedIncludeProcessor->toCodeString($resolvedInclude, $relativeFilePath)
            );
        }
        return $rewrites;
    }

    /**
     * @inheritDoc
     */
    public function getCurrentFileLogData(): array
    {
        return $this->currentFileLogData;
    }

    public function addFiles(string $moodleroot): void
    {

    }
}