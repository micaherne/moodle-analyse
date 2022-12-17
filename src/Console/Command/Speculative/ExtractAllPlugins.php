<?php

namespace MoodleAnalyse\Console\Command\Speculative;

use MoodleAnalyse\Codebase\Analyse\FileAnalyser;
use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Extract\PluginExtractor;
use MoodleAnalyse\PluginExtractionNotSupported;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ExtractAllPlugins extends \Symfony\Component\Console\Command\Command
{
    const DELETE = 'delete';
    protected static $defaultDescription = "Extract all supported plugins to a directory";

    private const MOODLE_DIR = 'moodle-dir';
    private const OUTPUT_DIR = 'output-dir';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('extract-all-plugins')
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, "The directory of the Moodle codebase")
            ->addArgument(self::OUTPUT_DIR, InputArgument::REQUIRED, "The parent directory for the plugin")
            ->addOption(self::DELETE, 'd', InputOption::VALUE_NONE, 'Remove the original plugin after extraction?');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);

        $moodleDirectory = $input->getArgument(self::MOODLE_DIR);
        if (!is_dir($moodleDirectory)) {
            throw new RuntimeException("Moodle directory $moodleDirectory does not exist");
        }

        $outputDir = $input->getArgument(self::OUTPUT_DIR);
        if (!is_dir($outputDir)) {
            $output->writeln("$outputDir does not exist");
            return Command::FAILURE;
        }

        $delete = $input->getOption(self::DELETE);

        $finder = new ComponentsFinder();
        $componentResolver = new ComponentResolver($moodleDirectory);
        $fileAnalyser = new FileAnalyser($componentResolver);

        $componentDirectories = $finder->getComponents($moodleDirectory);

        foreach ($componentDirectories as $component => $componentDirectory) {
            if ($component === 'core' || str_starts_with($component, 'core_')) {
                continue;
            }

            // Cache stores can be loaded before core_component is available (maybe?)
            if (str_starts_with($component, 'cachestore_')) {
                continue;
            }

            $logger->debug("Checking $component ($componentDirectory)");
            $fileFinder = new Finder();
            $subPluginDirs = $finder->getSubplugins($componentDirectory, $moodleDirectory);
            $fileFinder->in($moodleDirectory . '/' . $componentDirectory)
                ->name('*.php')->files();
            foreach ($subPluginDirs as $subPluginDir) {
                $fileFinder->exclude($subPluginDir);
            }

            foreach ($fileFinder as $file) {
                if (str_contains($file->getContents(), 'config.php')) {
                    // Check it's the actual root config.
                    $testFile = new SplFileInfo(
                        $file->getRealPath(),
                        $componentDirectory . '/' . $file->getRelativePath(),
                        $componentDirectory . '/' . $file->getRelativePathname()
                    );
                    $analysis = $fileAnalyser->analyseFile($testFile);
                    if (!$analysis->getIncludesConfig()) {
                        continue;
                    }
                    $logger->debug("Contains config");
                    continue 2;
                }
            }

            $resourcesFinder = new Finder();

            // The lib/javascript.php script that serves Javscript files currently checks that they're
            // under dirroot.
            $resourcesFinder->in($moodleDirectory . '/' . $componentDirectory)
                ->exclude(['amd', 'yui']) // Modules are loaded by requirejs.php / yui_combo.php which work fine.
                ->name(['*.js'])->files();

            foreach ($resourcesFinder as $ignored) {
                $logger->debug("Contains non modular JS");
                continue 2;
            }

            $componentOutputDir = $outputDir . '/' . $component;
            $output->writeln("Extracting $component to $componentOutputDir");

            $pluginExtractor = new PluginExtractor(new ConsoleLogger($output));
            try {
                $pluginExtractor->extractPlugin($moodleDirectory, $component, $componentOutputDir);
                if ($delete) {
                    $fs = new Filesystem();
                    $fs->remove($moodleDirectory . '/' . $componentDirectory);
                }
            } catch (PluginExtractionNotSupported $e) {
                $output->writeln("Unable to extract $component: " . $e->getMessage());
                $fs = new FileSystem();
                $fs->remove($componentOutputDir);
            }
        }

        return Command::SUCCESS;
    }


}