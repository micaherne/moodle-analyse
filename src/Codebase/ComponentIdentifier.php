<?php
declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

use Exception;

/**
 * Identify the component for a standard file path.
 *
 * Note that this only works with standard string paths. If dealing with resolvedInclude strings from
 * PathResolvingVisitor, use {@link ComponentResolver}.
 */
class ComponentIdentifier
{

    private string $moodleroot;
    private array $plugintypes;
    private array $subsystems;
    private array $subpluginLocations = [];

    /**
     * @param string $moodleroot
     */
    public function __construct(string $moodleroot)
    {
        $this->moodleroot = $moodleroot;
    }

    /**
     * @throws Exception
     */
    public function fileComponent(string $fileRelativePath): ?string
    {
        if (!is_null($this->moodleroot)) {
            $this->init();
        }

        $fileRelativePath = trim($fileRelativePath, '/');

        foreach ($this->plugintypes as $plugintype => $plugintypeDir) {
            if (str_starts_with($fileRelativePath, $plugintypeDir)) {
                $nextDir = substr($fileRelativePath, strlen($plugintypeDir) + 1);

                $parts = explode('/', $nextDir);

                // It's a file directly under a plugin type directory, either a subsystem or core probably.
                if (count($parts) === 1) {
                    continue;
                }

                $directSubdirectory = $parts[0];
                $mainPlugin = $plugintype . '_' . $directSubdirectory;
                if (count($parts) === 2) {
                    return $mainPlugin;
                }

                $subpluginLocations = $this->getSubpluginLocations($plugintypeDir . '/' . $directSubdirectory);

                foreach ($subpluginLocations as $subpluginType => $subpluginLocation) {
                    if (str_starts_with($fileRelativePath, $subpluginLocation)) {
                        $subPluginRelativePath = substr($fileRelativePath, strlen($subpluginLocation) + 1);
                        $firstSlashPosition = strpos($subPluginRelativePath, '/');
                        if ($firstSlashPosition === false) {
                            return $mainPlugin;
                        }
                        return $subpluginType . '_' . substr($subPluginRelativePath, 0, $firstSlashPosition);
                    }
                }

                if (file_exists($plugintypeDir . '/' . $directSubdirectory . '/version.php')) {
                    return $mainPlugin;
                }

            }
        }

        foreach ($this->subsystems as $subsystem => $subsystemDirectory) {
            if (str_starts_with($fileRelativePath, $subsystemDirectory . '/')) {
                return 'core_' . $subsystem;
            }
        }

        return 'moodle';
    }


    /**
     * @throws Exception
     */
    private function init(): void
    {
        $componentsJsonFile = $this->moodleroot . '/lib/components.json';

        if (!file_exists($componentsJsonFile)) {
            throw new Exception("File not found: $componentsJsonFile");
        }

        $componentsJson = json_decode(file_get_contents($componentsJsonFile));
        if (is_null($componentsJson)) {
            throw new Exception("Unable to read components.json");
        }

        $this->plugintypes = (array)$componentsJson->plugintypes;
        $this->subsystems = array_filter((array)$componentsJson->subsystems); // We don't need null ones.
    }

    /**
     * @param string $mainPluginDirectory
     * @return string[]
     * @throws Exception
     * @throws Exception
     */
    private function getSubpluginLocations(string $mainPluginDirectory): array
    {
        if (array_key_exists($mainPluginDirectory, $this->subpluginLocations)) {
            return $this->subpluginLocations[$mainPluginDirectory];
        }

        $subpluginsJsonFile = $this->moodleroot . '/' . $mainPluginDirectory . '/db/subplugins.json';
        if (file_exists($subpluginsJsonFile)) {
            $subplugins = json_decode(file_get_contents($subpluginsJsonFile));
            if (is_null($subplugins)) {
                throw new Exception("Unable to parse $subpluginsJsonFile");
            }
            $this->subpluginLocations[$mainPluginDirectory] = (array)$subplugins->plugintypes;
        } else {
            $this->subpluginLocations[$mainPluginDirectory] = [];
        }

        return $this->subpluginLocations[$mainPluginDirectory];
    }

}