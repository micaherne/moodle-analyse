<?php

namespace MoodleAnalyse\Console\Command\Speculative;

use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use MoodleAnalyse\Extract\PluginExtractor;
use MoodleAnalyse\PluginExtractionNotSupported;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ExtractAllPlugins extends \Symfony\Component\Console\Command\Command
{
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
            ->addArgument(self::OUTPUT_DIR, InputArgument::REQUIRED, "The parent directory for the plugin");

    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moodleDirectory = $input->getArgument(self::MOODLE_DIR);
        if (!is_dir($moodleDirectory)) {
            throw new RuntimeException("Moodle directory $moodleDirectory does not exist");
        }

        $outputDir = $input->getArgument(self::OUTPUT_DIR);
        if (!is_dir($outputDir)) {
            $output->writeln("$outputDir does not exist");
            return Command::FAILURE;
        }

        $finder = new ComponentsFinder();

        $componentDirectories = $finder->getComponents($moodleDirectory);

        foreach ($componentDirectories as $component => $componentDirectory) {
            if ($component === 'core' || str_starts_with($component, 'core_')) {
                continue;
            }
            // $output->writeln("Checking $component ($componentDirectory)");
            $fileFinder = new Finder();
            $subPluginDirs = $finder->getSubplugins($componentDirectory, $moodleDirectory);
            $fileFinder->in($moodleDirectory . '/' . $componentDirectory)
                ->name('*.php')->files();
            foreach ($subPluginDirs as $subPluginDir) {
                $fileFinder->exclude($subPluginDir);
            }
            foreach ($fileFinder as $file) {
                if (str_contains($file->getContents(), 'config.php')) {
                    continue 2;
                }
            }
            $resourcesFinder = new Finder();

            $resourcesFinder->in($moodleDirectory . '/' . $componentDirectory)
                ->name(['*.js', '*.css'])->files();
            foreach ($resourcesFinder as $file) {
                continue 2;
            }

            $componentOutputDir = $outputDir . '/' . $component;
            $output->writeln("Extracting $component to $componentOutputDir");

            $pluginExtractor = new PluginExtractor(new ConsoleLogger($output));
            try {
                $pluginExtractor->extractPlugin($moodleDirectory, $component, $componentOutputDir);
            } catch (PluginExtractionNotSupported $e) {
                $output->writeln("Unable to extract $component: " . $e->getMessage());
                $fs = new FileSystem();
                $fs->remove($componentOutputDir);
            }


        }

        return Command::SUCCESS;
    }


}