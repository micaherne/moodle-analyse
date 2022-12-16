<?php

namespace MoodleAnalyse\Extract;

use MoodleAnalyse\Codebase\Analyse\FileAnalyser;
use MoodleAnalyse\Codebase\Component\ComponentsFinder;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\PathCategory;
use MoodleAnalyse\Codebase\Rewrite\RewriteApplier;
use MoodleAnalyse\PluginExtractionNotSupported;
use MoodleAnalyse\Rewrite\GetComponentPathRewrite;
use MoodleAnalyse\Rewrite\RelativeDirPathRewrite;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PluginExtractor
{

    /**
     * @var \MoodleAnalyse\Codebase\Rewrite\RewriteApplier|mixed
     */
    public $rewriteApplier;

    public function __construct(private LoggerInterface $logger = new NullLogger())
    {
        $this->rewriteApplier = new RewriteApplier($this->logger);
    }

    /**
     * @param string $moodleDirectory
     * @param string $componentName
     * @param string $outputDirectory
     * @return void
     * @throws \Exception
     */
    public function extractPlugin(string $moodleDirectory, string $componentName, string $outputDirectory): void
    {
        $componentsFinder = new ComponentsFinder();
        $componentPath = null;
        foreach ($componentsFinder->getComponents($moodleDirectory) as $component => $relativeComponentPath) {
            if ($component === $componentName) {
                $componentPath = $relativeComponentPath;
                break;
            }
        }
        if (is_null($componentPath)) {
            throw new PluginExtractionNotSupported("Component path for $componentName not found");
        }

        $componentPathFull = $moodleDirectory . '/' . $componentPath;

        $fs = new Filesystem();

        $fs->mirror($componentPathFull, $outputDirectory);
        foreach (
            $componentsFinder->getSubplugins(
                $componentPath,
                $moodleDirectory
            ) as $subpluginType => $subpluginPath
        ) {
            $fs->remove($outputDirectory . '/' . $subpluginPath);
        }

        $finder = new Finder();
        $finder->in($outputDirectory)->name('*.php')->files();

        $componentResolver = new ComponentResolver($moodleDirectory);
        $fileAnalyser = new FileAnalyser($componentResolver);

        foreach ($finder as $file) {
            $relativeFile = new SplFileInfo(
                $file->getRealPath(),
                $componentPath . '/' . $file->getRelativePath(),
                $componentPath . '/' . $file->getRelativePathname()
            );

            $this->logger->debug("Analysing file: {$file->getRelativePathname()}");

            $rewrites = [];
            $analysis = $fileAnalyser->analyseFile($relativeFile);
            foreach ($analysis->getCodebasePaths() as $codebasePath) {
                $code = $codebasePath->getPathCode()->getPathCode();
                if ($codebasePath->getPathCategory() === PathCategory::SimpleFile) {
                    $resolvedComponent = $componentResolver->resolveComponent(
                        $codebasePath->getPathCode()->getResolvedPath()
                    );
                    if (is_null($resolvedComponent)) {
                        $this->logger->warning("Can't resolve component for $code");
                        continue;
                    }
                    if (implode('_', array_slice($resolvedComponent, 0, 2)) === $componentName) {
                        $this->logger->debug("Rewriting path from same component: $code");
                        // We can't just ignore this, we need to rewrite it to use __DIR__
                        $rewrites[] = new RelativeDirPathRewrite($codebasePath, $componentPath);
                        continue;
                    }
                    $rewrite = new GetComponentPathRewrite($codebasePath->getPathCode());
                    $rewrites[] = $rewrite;
                    $this->logger->info("Rewriting $code to " . $rewrite->getCode());
                } elseif ($codebasePath->getFileComponent() === $codebasePath->getPathCode()->getPathComponent()) {
                    if (str_starts_with($code, '__DIR__')) {
                        // No need to do anything as it's a __DIR__ path to the same component.
                        continue;
                    }
                    $rewrites[] = new RelativeDirPathRewrite($codebasePath, $componentPath);
                } else {
                    if (str_starts_with($code, '\core_component::get_component_path(')) {
                        continue;
                    }
                    throw new PluginExtractionNotSupported("Non simple file found: $code");
                }
            }

            if ($rewrites === []) {
                $this->logger->debug("Nothing to apply");
                continue;
            } else {
                $this->logger->debug("Applying rewrites");
                $this->rewriteApplier->applyRewrites($rewrites, $file);
            }
        }
    }

}