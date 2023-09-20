<?php

namespace MoodleAnalyse\Console\Command\Speculative;

use MoodleAnalyse\Codebase\Analyse\CodebaseAnalyser;
use MoodleAnalyse\Codebase\Analyse\Rewrite\FileRewriteAnalysis;
use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\Rewrite\RewriteApplier;
use MoodleAnalyse\Rewrite\Provider\ExtractComponentPackagesProvider;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class ExtractComponentPackages extends Command
{
    protected static $defaultDescription = 'Extract all components to a directory, for use by a front controller';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('extract-component-packages')
            ->addArgument('moodle-dir', InputArgument::REQUIRED, 'The directory of the Moodle codebase')
            ->addArgument('output-dir', InputArgument::REQUIRED, 'The parent directory for the components')
            ->addOption('rewrite-log', 'r', InputOption::VALUE_REQUIRED, 'The file to write the rewrite log to', null)
            ->addOption('single-file', 'f', InputOption::VALUE_REQUIRED, 'The single file to rewrite', null)
            ->addOption('no-copy', null, InputOption::VALUE_NONE, 'Don\'t copy the files, just rewrite them (for testing only)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);

        $moodleDirectory = $input->getArgument('moodle-dir');
        $outputDir = $input->getArgument('output-dir');
        $rewriteLog = $input->getOption('rewrite-log');
        $noCopy = $input->getOption('no-copy');
        $singleFile = $input->getOption('single-file');

        if ($rewriteLog && is_file($rewriteLog)) {
            unlink($rewriteLog);
        }

        if (!is_dir($moodleDirectory) || !file_exists($moodleDirectory . '/version.php')) {
            throw new RuntimeException("Moodle directory $moodleDirectory does not exist");
        }

        if (!is_dir($outputDir)) {
            $output->writeln("$outputDir does not exist");
            return Command::FAILURE;
        }

        if (!is_null($singleFile) && !is_file($moodleDirectory . '/' . $singleFile)) {
            $output->writeln("$singleFile does not exist");
            return Command::FAILURE;
        }

        $testProcess = new Process(['rsync', '--help']);
        $testProcess->run();

        if ($testProcess->isSuccessful()) {
            $logger->info("rsync is installed");
        } else {
            $logger->info("rsync is not installed");
            return Command::FAILURE;
        }

        $finder = new ComponentsFinder();

        $componentDirectories = iterator_to_array($finder->getComponents($moodleDirectory));

        $corePackageComponents = $this->getCorePackageComponents($componentDirectories);

        $corePackageComponentDirs = array_fill_keys($corePackageComponents, null);

        // Check they're all there.
        foreach ($componentDirectories as $component => $componentDirectory) {
            if (array_key_exists($component, $corePackageComponentDirs)) {
                $corePackageComponentDirs[$component] = $componentDirectory;
            }
        }

        if (in_array(null, $corePackageComponentDirs)) {
            $logger->error("Not all core package components were found");
            return Command::FAILURE;
        }

        // Do the core package components first.
        $coreDestination = realpath($outputDir) . '/moodle-core';

        $command = [
            'rsync',
            '-a',
            '--del',
            '--delete-excluded',
            '--exclude=**/.git',
            '--exclude=**/.gitignore',
            '--exclude=/vendor/',
            '--exclude=/node_modules/',
        ];
        foreach ($componentDirectories as $component => $componentDirectory) {
            if (!array_key_exists($component, $corePackageComponentDirs)) {
                $command[] = '--exclude=' . $componentDirectory . '/';
            }
        }

        $command[] = $moodleDirectory . '/';
        $command[] = $coreDestination;

        $process = new Process($command);
        $process->setTimeout(3600);

        if (!$noCopy) {
            $startTime = microtime(true);
            $process->mustRun();
            $endTime = microtime(true);
            $output->writeln("Core package components copied in " . ($endTime - $startTime) . " seconds");
        }

        // Now do the rest.
        foreach ($componentDirectories as $component => $componentDirectory) {
            if (array_key_exists($component, $corePackageComponentDirs)) {
                continue;
            }

            $destination = realpath($outputDir) . '/moodle-' . $component . '/';
            $command = [
                'rsync',
                '-a',
                '--del',
                '--delete-excluded',
                '--exclude=**/.git',
                '--exclude=**/.gitignore',
            ];

            $checkSubdirectories = $componentDirectories;
            foreach ($checkSubdirectories as $checkComponent => $checkSubdirectory) {
                if ($component == $checkComponent) {
                    continue;
                }
                if (str_starts_with($checkSubdirectory, $componentDirectory)) {
                    $command[] = '--exclude=' . substr($checkSubdirectory, strlen($componentDirectory)) . '/';
                }
            }

            $command[] = $moodleDirectory . '/' . $componentDirectory . '/';
            $command[] = $destination;

            if (!$noCopy) {
                $output->writeln("Copying $component to $destination");
                $process = new Process($command);
                $process->mustRun();
            }

        }

        // Add the fake core_root and core_lib to the list of directories as this will be identified
        // as a component by ComponentResolver. This must happen after the rsync as otherwise
        // it will try to extract core_root too (i.e. the whole codebase).
        $corePackageComponents[] = 'core_root';
        $corePackageComponents[] = 'core_lib';

        // Rewrite the PHP files where necessary.
        $componentResolver = new ComponentResolver($moodleDirectory);
        $provider = new ExtractComponentPackagesProvider($componentResolver, $logger);
        $provider->setCoreComponents($corePackageComponents);
        $provider->setComponentDirectories($componentDirectories);

        $rewriteApplier = new RewriteApplier($logger);

        // Collect routes to executable scripts.
        // This does not catch things like lib/ajax/service-nologin.php (which includes another script)
        // or install.php but we should be able to find those in the root package.
        $webRoutes = [];
        $cliRoutes = [];

        $codebaseAnalyser = new CodebaseAnalyser($moodleDirectory);
        if ($singleFile !== null) {
            $analysisIterator = $codebaseAnalyser->analyseFile($singleFile);
        } else {
            $analysisIterator = $codebaseAnalyser->analyseAll();
        }
        foreach ($analysisIterator as $fileAnalysis) {

            // On no account rewrite the component.php file.
            // TODO: Do we need to rewrite the directory separators?
            if ($fileAnalysis->getRelativePath() === 'lib/classes/component.php') {
                continue;
            }

            if (!is_null($singleFile) && $fileAnalysis->getRelativePath() !== $singleFile) {
                continue;
            }

            $logger->debug("Getting rewrites for " . $fileAnalysis->getRelativePath());

            $rewriteAnalysis = $provider->analyseFileForRewrite($fileAnalysis);

            if ($rewriteLog !== null) {
                $this->writeRewriteLog($rewriteLog, $rewriteAnalysis);
            }

            $componentResolved = $componentResolver->resolveComponent($fileAnalysis->getRelativePath());
            $relativePath = $fileAnalysis->getRelativePath();
            if ($componentResolved === null) {
                $fileToRewrite = $coreDestination . '/' . $fileAnalysis->getRelativePath();
            } else {
                $component = rtrim("{$componentResolved[0]}_{$componentResolved[1]}", '_');
                $relativePath = $componentResolved[2];
                if (in_array($component, $corePackageComponents)) {
                    $fileToRewrite = $coreDestination . '/' . $fileAnalysis->getRelativePath();
                } else {
                    $fileToRewrite = realpath($outputDir) . '/moodle-' . $component . '/' . $relativePath;
                }
            }

            // Add to routes if necessary.
            if (!str_starts_with($fileToRewrite, realpath($outputDir))) {
                throw new \Exception("File to rewrite $fileToRewrite is not in the output directory " . realpath($outputDir));
            }

            $fileToRewriteRelative = substr($fileToRewrite, strlen(realpath($outputDir)) + 1);
            $fileToRewriteRelative = ltrim($fileToRewriteRelative, '/');

            if ($fileAnalysis->isCliScript()) {
                $cliRoutes[$fileAnalysis->getRelativePath()] = $fileToRewriteRelative;
            } elseif ($fileAnalysis->getIncludesConfig()) {
                $webRoutes[$fileAnalysis->getRelativePath()] = $fileToRewriteRelative;
            }

            $rewrites = $rewriteAnalysis->getRewrites();
            if ($rewrites === []) {
                continue;
            }

            // For quicker debugging, copy only the file to be rewritten.
            if ($noCopy) {
                $source = $fileAnalysis->getFinderFile()->getRealPath();
                $logger->debug("Copying $source to $fileToRewrite");
                copy($source, $fileToRewrite);
            }

            $logger->info("Rewriting " . $fileAnalysis->getRelativePath() . ' to ' . $fileToRewrite);

            if (!str_starts_with(realpath($fileToRewrite), realpath($outputDir))) {
                throw new \Exception("File to rewrite $fileToRewrite is not in the output directory " . realpath($outputDir));
            }

            $fileInfo = new SplFileInfo($fileToRewrite, dirname($relativePath), $relativePath);
            $rewriteApplier->applyRewrites($rewrites, $fileInfo);
        }

        // Write route files.
        $webRoutesFile = realpath($outputDir) . '/web-routes.php';
        $cliRoutesFile = realpath($outputDir) . '/cli-routes.php';
        file_put_contents($webRoutesFile, '<?php return ' . var_export($webRoutes, true) . ';');
        file_put_contents($cliRoutesFile, '<?php return ' . var_export($cliRoutes, true) . ';');

        return Command::SUCCESS;
    }

    /**
     * Get a list of the components that are part of the core package.
     * @param array<string, string> $componentDirectories The list of components and their directories.
     * @return array<string> The list of components that are part of the core package.
     */
    private function getCorePackageComponents(array $componentDirectories): array
    {
        // TODO: The core components in this list aren't necessary anymore, just keeping it for the time
        //       being to remember the comments on the dependencies.
        $corePackageComponents = [
            'core',
            'core_admin',
            'core_cache',
            'core_course',
            // Because there's a stupid dependency on it (for a string!) in lib/db/caches.php
            // (like why is the cache even there, not in core_course?)
            'core_message',
            // Because messagelib.php has a dependency on it.
            'tool_phpunit',
            // Has executable scripts which don't include config.php for a while
            'tool_behat',
            // Has executable scripts which don't include config.php for a while
            'cachestore_file',
            'cachestore_session',
            'cachestore_static',
            'cachelock_file',
            // Some cache stuff is loaded before core_component.
            'theme_boost',
            // Required for installation as it's the default theme (probably doesn't need to be here really as it'll be loaded after core_component).
        ];

        foreach ($componentDirectories as $component => $componentDirectory) {
            if (str_starts_with($component, 'core')) {
                $corePackageComponents[] = $component;
            }
        }

        return $corePackageComponents;
    }

    private function writeRewriteLog(
        mixed $rewriteLog,
        FileRewriteAnalysis $rewriteAnalysis
    ) {
        $out = fopen($rewriteLog, 'a');

        if ($out === false) {
            throw new RuntimeException("Unable to open rewrite log $rewriteLog");
        }

        foreach ($rewriteAnalysis->getCodebasePathRewriteAnalyses() as $analysis) {
            $rewrite = $analysis->getRewrite();
            $startPos = null;
            $code = null;
            if ($rewrite !== null) {
                $startPos = $rewrite->getStartPos();
                $code = $rewrite->getCode();
            }
            fputcsv($out, [
                $rewriteAnalysis->getFileAnalysis()->getRelativePath(),
                $rewriteAnalysis->getFileAnalysis()->getFileComponent(),
                $analysis->getCodebasePath()->getRelativeFilename(),
                $analysis->getCodebasePath()->getPathCode()->getPathCodeStartLine(),
                $analysis->getCodebasePath()->getPathCode()->getPathCodeEndLine(),
                $analysis->getCodebasePath()->getPathCode()->getPathComponent(),
                $analysis->getCodebasePath()->getPathCode()->getPathCode(),
                $analysis->getCodebasePath()->getPathCode()->getResolvedPath(),
                $startPos,
                $code,
                $analysis->getExplanation(),
                $analysis->isWorthInvestigating() ? 'true' : 'false',
                $analysis->getCodebasePath()->getPathCategory()?->name ?? '',
                $analysis->getCodebasePath()->isFromCoreComponent() ? 'true' : 'false',
                $analysis->getCodebasePath()->isAssignedFromPreviousPathVariable() ? 'true' : 'false',
            ]);
        }

        fclose($out);
    }

}