<?php

declare(strict_types=1);

namespace MoodleAnalyse\Console;

use MoodleAnalyse\Console\Command\Speculative\ExtractAllPlugins;
use MoodleAnalyse\Console\Command\Speculative\ExtractPlugin;
use MoodleAnalyse\Console\Command\Speculative\RewriteForPluginExtraction;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct('moodle-analyse', '1.0');
    }

    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Command\FindCodebasePaths();
        $commands[] = new Command\RewriteCommand();
        $commands[] = new Command\Speculative\ExtractablePlugins();
        $commands[] = new ExtractPlugin();
        $commands[] = new RewriteForPluginExtraction();
        $commands[] = new ExtractAllPlugins();
        return $commands;
    }


}