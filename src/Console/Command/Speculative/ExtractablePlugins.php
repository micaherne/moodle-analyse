<?php

namespace MoodleAnalyse\Console\Command\Speculative;

use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Find plugins that have no "entry point" files that include config.php
 *
 * This is really dumb at the moment but it will do to find some simple plugins that can be extracted.
 */
class ExtractablePlugins extends Command
{

    private const MOODLE_DIR = 'moodle-dir';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName("extractable-plugins")
            ->setDescription("Find plugins with no pages or CLI scripts etc.")
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, "The directory of the Moodle codebase");
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
            $output->writeln("$component ($componentDirectory)");
        }

        return 0;
    }

}