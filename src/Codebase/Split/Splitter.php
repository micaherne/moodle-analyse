<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase\Split;

use DirectoryIterator;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Splitter
{

    private LoggerInterface $logger;

    private array $ignoreddirs = [
        'CVS' => true,
        '_vti_cnf' => true,
        'amd' => true,
        'classes' => true,
        'db' => true,
        'fonts' => true,
        'lang' => true,
        'pix' => true,
        'simpletest' => true,
        'templates' => true,
        'tests' => true,
        'yui' => true,
    ];

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    private function getComponentPaths(string $moodleroot): object
    {
        $components = json_decode(file_get_contents($moodleroot . '/lib/components.json'));
        foreach (['mod', 'editor', 'tool', 'local'] as $plugintype) {
            $plugintyperoot = $moodleroot . '/' . $components->plugintypes->$plugintype;
            foreach (glob($plugintyperoot . '/*/db/subplugins.json') as $subpluginsfile) {
                $data = json_decode(file_get_contents($subpluginsfile));
                if (!$data) {
                    continue;
                }
                foreach ($data->plugintypes as $plugintype => $relativepath) {
                    $components->plugintypes->$plugintype = $relativepath;
                }
            }
        }

        return $components;
    }

    public function splitCodebase(string $moodleRoot, string $outputDir)
    {
        if (!file_exists($outputDir)) {
            mkdir($outputDir);
        }
        $componentPaths = $this->getComponentPaths($moodleRoot);

        $components = [];
        foreach ($componentPaths->plugintypes as $pluginType => $pluginTypeRelativePath) {
            $fulldir = $moodleRoot . '/' . $pluginTypeRelativePath;
            $items = new DirectoryIterator($fulldir);
            /** @var DirectoryIterator $item */
            foreach ($items as $item) {
                if ($item->isDot() || !$item->isDir()) {
                    continue;
                }
                $pluginName = $item->getFilename();
                if (array_key_exists(
                        $pluginName,
                        $this->ignoreddirs
                    ) && !($pluginType === 'auth' && $pluginName === 'db')) {
                    continue;
                }
                $components[$pluginTypeRelativePath . '/' . $pluginName] = $pluginType . '_' . $pluginName;
            }
        }

        foreach ($componentPaths->subsystems as $subsystem => $subsystemRelativePath) {
            if (is_null($subsystemRelativePath)) {
                continue;
            }
            $components[$subsystemRelativePath] = 'core_' . $subsystem;
        }

        $components += [
            'lib' => 'core_lib',
            '' => 'core_root'
        ];

        $fs = new Filesystem();

        foreach ($components as $relativePath => $component) {
            $subcomponents = array_filter(
                $components,
                fn($path) => !($path === $relativePath) && ($relativePath === '' || str_starts_with(
                            $path,
                            $relativePath . '/'
                        )),
                ARRAY_FILTER_USE_KEY
            );
            $this->logger->info("Copying $component ($relativePath)");
            if (count($subcomponents) > 0) {
                $this->logger->info("Ignoring subcomponents: " . implode(', ', $subcomponents));
            }
            $componentOutputDir = $outputDir . '/moodle-' . $component;

            if ($fs->exists($componentOutputDir)) {
                $this->logger->info("Deleting $componentOutputDir");
                $fs->remove($componentOutputDir);
            }

            $fs->mkdir($componentOutputDir);
            $finder = new Finder();
            $finder->ignoreVCS(true);
            $fullExcludePaths = array_keys($subcomponents);

            // There's no leading slash for core_root.
            if ($component === 'core_root') {
                $relativeExcludePaths = $fullExcludePaths;
            } else {
                $relativeExcludePaths = array_map(
                    fn($path) => substr($path, strlen($relativePath) + 1),
                    $fullExcludePaths
                );
            }

            $relativeExcludePaths = array_merge($relativeExcludePaths, ['vendor', 'node_modules']);

            $finder->in($moodleRoot . '/' . $relativePath)->exclude($relativeExcludePaths)->files();
            try {
                foreach ($finder as $file) {
                    $fs->copy($file->getPathname(), $componentOutputDir . '/' . $file->getRelativePathname());
                }
            } catch (Exception $e) {
                $this->logger->error("ERROR: Copying $component failed: {$e->getMessage()}");
            }
        }
    }
}