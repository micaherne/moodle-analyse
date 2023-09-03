<?php

namespace MoodleAnalyse\Console\Command\Speculative;

use MoodleAnalyse\Codebase\Analyse\CodebaseAnalyser;
use MoodleAnalyse\Codebase\Analyse\FileAnalysis;
use MoodleAnalyse\Codebase\PathCategory;
use MoodleAnalyse\Codebase\Rewrite\RewriteApplier;
use MoodleAnalyse\Rewrite\GetComponentPathRewrite;
use MoodleAnalyse\Rewrite\GetPathFromRelativeRewrite;
use MoodleAnalyse\Rewrite\Rewrite;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command rewrites Moodle so that requires and other file links to plugin code use core_component.
 *
 * It makes a few assumptions:
 *
 * 1. $CFG->dirroot will continue to be available as the root of the Moodle codebase.
 * 2. All core components will continue to be under $CFG->dirroot.
 */
class RewriteForPluginExtraction extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultDescription = 'Rewrite Moodle codebase to remove reliance on dirroot from links to and from plugin code';

    const MOODLE_DIR = 'moodle-dir';
    private LoggerInterface $logger;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('rewrite-for-plugin-extraction')
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, 'the root of the Moodle installation');

    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moodleDirectory = $input->getArgument(self::MOODLE_DIR);
        if (!is_dir($moodleDirectory)) {
            $output->writeln("Moodle directory $moodleDirectory does not exist");
            return Command::FAILURE;
        }

        $this->logger = new ConsoleLogger($output);

        $rewriteApplier = new RewriteApplier($this->logger);

        $codebaseAnalyser = new CodebaseAnalyser($moodleDirectory);
        foreach ($codebaseAnalyser->analyseAll() as $fileAnalysis) {

            $this->logger->debug("Getting rewrites for " . $fileAnalysis->getRelativePath());

            $rewrites = $this->getRewrites($fileAnalysis);
            if ($rewrites === []) {
                continue;
            }

            $this->logger->info("Rewriting " . $fileAnalysis->getRelativePath());

            $rewriteApplier->applyRewrites($rewrites, $fileAnalysis->getFinderFile());
        }

        return Command::SUCCESS;
    }

    /**
     * @param FileAnalysis $fileAnalysis
     * @return array<Rewrite>
     */
    private function getRewrites(FileAnalysis $fileAnalysis): array
    {
        $rewrites = [];

        foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {

            $pathCode = $codebasePath->getPathCode();
            $pathComponent = $pathCode->getPathComponent();

            // Ignore dirroot wrangling.
            if ($codebasePath->getPathCategory() === PathCategory::DirRoot) {
                continue;
            }

            // Ignore config includes.
            if ($codebasePath->getPathCategory() === PathCategory::Config) {
                continue;
            }

            // Any dynamic plugin name, e.g. enrol_{$plugin}, but not components which are entirely dynamic.
            if (!is_null($pathComponent) && str_contains($pathComponent, '_{$')) {
                $rewrites[] = new GetComponentPathRewrite($pathCode);
            } elseif ($codebasePath->getPathCategory() === PathCategory::FullRelativePath) {
                $rewrites[] = new GetPathFromRelativeRewrite($pathCode);
            } elseif ($codebasePath->getPathCategory() === PathCategory::SimpleFile) {

                // Don't rewrite anything within a component.
                if ($pathComponent === $codebasePath->getFileComponent()) {
                    continue;
                }

                if ($this->isPluginPath($pathComponent)) {
                    $rewrites[] = new GetComponentPathRewrite($pathCode);
                }
            }

        }

        return $rewrites;
    }

    /**
     * @param string|null $pathComponent
     * @return bool
     */
    private function isPluginPath(?string $pathComponent): bool
    {
        return !is_null($pathComponent) && $pathComponent !== 'core' && !str_starts_with($pathComponent, 'core_');
    }

}