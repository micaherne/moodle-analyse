<?php

use MoodleAnalyse\Rewrite\RewriteCanonical;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
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

        $rewriter = new RewriteCanonical(__DIR__ . '/../moodle', $logger);

        $rewriter->rewrite();

        return 0;
    }

});

$app->setDefaultCommand('rewrite:1-canonical', true);

$app->run();

