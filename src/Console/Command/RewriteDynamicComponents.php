<?php

declare(strict_types=1);

namespace MoodleAnalyse\Console\Command;

use MoodleAnalyse\Codebase\Analyse\CodebaseAnalyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RewriteDynamicComponents extends Command
{
    private const MOODLE_DIR = 'moodle-dir';

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName("rewrite-dynamic-components")
            ->setDescription("Rewrite internal Moodle paths with variables")
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, "The directory of the Moodle codebase");

    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moodleDirectory = $input->getArgument(self::MOODLE_DIR);
        if (!is_dir($moodleDirectory)) {
            throw new RuntimeException("Moodle directory $moodleDirectory does not exist");
        }

        $codebaseAnalyser = new CodebaseAnalyser($moodleDirectory);
        foreach ($codebaseAnalyser->analyseAll() as $fileAnalysis) {
            foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {
                $pathCode = $codebasePath->getPathCode();
                $pathComponent = $pathCode->getPathComponent();
                if (is_null($pathComponent)) {
                    continue;
                }
                if (str_contains($pathComponent, '$')) {
                    $output->writeln($fileAnalysis->getRelativePath());
                    $output->writeln("{$pathCode->getPathCode()}");
                }
            }
        }

        return 0;
    }

}