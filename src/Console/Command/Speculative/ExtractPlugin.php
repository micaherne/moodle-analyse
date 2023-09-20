<?php

namespace MoodleAnalyse\Console\Command\Speculative;

use MoodleAnalyse\Extract\PluginExtractor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extract a plugin to a directory.
 *
 * @deprecated This is superseded by ExtractComponentPackages
 */
class ExtractPlugin extends Command
{
    private const MOODLE_DIR = 'moodle-dir';
    const OUTPUT_DIR = 'output-dir';
    const COMPONENT_NAME = 'component-name';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName("extract-plugin")
            ->setDescription("Extract and rewrite a plugin from Moodle")
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, "The directory of the Moodle codebase")
            ->addArgument(self::COMPONENT_NAME, InputArgument::REQUIRED, "The frankenstyle name of the plugin")
            ->addArgument(self::OUTPUT_DIR, InputArgument::REQUIRED, "The output directory for the plugin (not the parent!)");

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
        if (!is_dir(dirname($outputDir))) {
            throw new RuntimeException("Parent directory of $outputDir does not exist");
        }
        if (is_dir($outputDir)) {
            throw new RuntimeException("$outputDir already exists");
        }

        $componentName = $input->getArgument(self::COMPONENT_NAME);

        $pluginExtractor = new PluginExtractor(new ConsoleLogger($output));
        $pluginExtractor->extractPlugin($moodleDirectory, $componentName, $outputDir);

        return 0;
    }

}