<?php

declare(strict_types=1);

namespace MoodleAnalyse\Console;

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
        $commands[] = new Command\RewriteDynamicComponents();
        return $commands;
    }


}