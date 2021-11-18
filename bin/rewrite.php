<?php

use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\ResolvedIncludeProcessor;
use MoodleAnalyse\Rewrite\RewriteEngine;
use MoodleAnalyse\Rewrite\Strategy\CanonicalStrategy;
use MoodleAnalyse\Rewrite\Strategy\CoreCodebaseStrategy;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();
$app->add(new class() extends Command {

    protected function configure()
    {
        $this->setName('rewrite:1-canonical')
            ->setDescription("In-place rewrite with tidied versions of paths")
            ->addArgument('moodle-dir', InputArgument::REQUIRED, 'The Moodle directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);

        $resolvedIncludeProcessor = new ResolvedIncludeProcessor();
        $strategy = new CanonicalStrategy($logger, $resolvedIncludeProcessor);

        $rewriter = new RewriteEngine('\\\\wsl$\Ubuntu-20.04\home\michael\dev\moodle\moodle-rewrite',
            $logger,
            $strategy
        ); // __DIR__ . '/../moodle', $logger);

        $rewriter->rewrite();

        return 0;
    }

});

$app->add(new class() extends Command {

    protected function configure()
    {
        $this->setName('rewrite:2-corecodebase')
            ->setDescription("In-place rewrite with core_codebase static calls")
            ->addArgument('moodle-dir', InputArgument::REQUIRED, 'The Moodle directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);

        // Hard-coded for testing but should come from the moodle-dir argument.
        $moodleroot = '\\\\wsl$\Ubuntu-20.04\home\michael\dev\moodle\moodle-rewrite';

        $componentResolver = new ComponentResolver($moodleroot);
        $resolvedIncludeProcessor = new ResolvedIncludeProcessor($componentResolver);

        $strategy = new CoreCodebaseStrategy($logger, $resolvedIncludeProcessor, $componentResolver);

        $rewriter = new RewriteEngine(
            $moodleroot,
            $logger,
            $strategy
        ); // __DIR__ . '/../moodle', $logger);

        $rewriter->rewrite();

        return 0;
    }

});

// $app->setDefaultCommand('rewrite:1-canonical', true);

$app->run();

