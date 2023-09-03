<?php

declare(strict_types=1);

namespace MoodleAnalyse\Console\Command;

use MoodleAnalyse\Codebase\Analyse\CodebaseAnalyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rewrite the Moodle codebase using a particular rewriter.
 *
 * @deprecated This was kind of sucky and is no longer used.
 */
class RewriteCommand extends Command
{
    private const MOODLE_DIR = 'moodle-dir';
    const REWRITER_CLASS = 'rewriter-class';

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName("rewrite")
            ->setDescription("Rewrite internal Moodle paths")
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, "The directory of the Moodle codebase")
            ->addArgument(
                self::REWRITER_CLASS, InputArgument::REQUIRED, 'The class under Codebase\Rewrite\Rewriter to use');

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

        $rewriterClass = $input->getArgument(self::REWRITER_CLASS);
        $rewriterClassName = '\MoodleAnalyse\Codebase\Rewrite\Rewriter\\' . $rewriterClass;
        if (!class_exists($rewriterClassName)) {
            throw new RuntimeException("Class $rewriterClass does not exist");
        }

        $rewriter = new $rewriterClassName(new ConsoleLogger($output));
        $codebaseAnalyser = new CodebaseAnalyser($moodleDirectory);
        foreach ($codebaseAnalyser->analyseAll() as $fileAnalysis) {
            $rewriter->rewrite($fileAnalysis);
        }

        return 0;
    }

}