<?php

use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\ResolvedIncludeProcessor;
use MoodleAnalyse\Codebase\Split\Splitter;
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
$app->add(
    new class() extends Command {

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

            $rewriter = new RewriteEngine(
                '\\\\wsl$\Ubuntu-20.04\home\michael\dev\moodle\moodle-rewrite',
                $logger,
                $strategy
            ); // __DIR__ . '/../moodle', $logger);

            $rewriter->rewrite();

            return 0;
        }

    }
);

$app->add(
    new class() extends Command {

        protected function configure()
        {
            $this->setName('rewrite:2-corecodebase')
                ->setDescription("In-place rewrite with core_codebase static calls")
                ->addArgument('moodle-dir', InputArgument::REQUIRED, 'The Moodle directory');
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $logger = new ConsoleLogger($output);

            $moodleroot = $input->getArgument('moodle-dir');

            $componentResolver = new ComponentResolver($moodleroot);
            $resolvedIncludeProcessor = new ResolvedIncludeProcessor($componentResolver);

            $strategy = new CoreCodebaseStrategy($logger, $resolvedIncludeProcessor, $componentResolver);

            $rewriter = new RewriteEngine(
                $moodleroot,
                $logger,
                $strategy
            );

            $rewriter->rewrite();

            return 0;
        }

    }
);

$app->add(
    new class() extends Command {

        const ARG_MOODLE_DIR = 'moodle-dir';
        const ARG_OUTPUT_DIR = 'output-dir';

        protected function configure()
        {
            $this->setName('split')
                ->setDescription("Split codebase into basic directory per component")
                ->addArgument(self::ARG_MOODLE_DIR, InputArgument::REQUIRED, 'The Moodle directory')
                ->addArgument(self::ARG_OUTPUT_DIR, InputArgument::REQUIRED, 'The output parent directory');
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $logger = new ConsoleLogger($output);
            $splitter = new Splitter($logger);
            $start = new DateTimeImmutable();
            $splitter->splitCodebase(
                $input->getArgument(self::ARG_MOODLE_DIR),
                $input->getArgument(self::ARG_OUTPUT_DIR)
            );
            $timeTaken = (new DateTimeImmutable())->diff($start);
            $logger->info("Finished in " . $timeTaken->format('i:S'));
            return 0;
        }


    }
);

// $app->setDefaultCommand('rewrite:1-canonical', true);

$app->run();

