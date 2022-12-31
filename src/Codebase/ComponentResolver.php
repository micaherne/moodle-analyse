<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

/**
 * Resolves paths returned in resolvedInclude format into the component they belong to.
 */
class ComponentResolver
{

    const SUBSYSTEM_ROOT = '#subsystemRoot';
    const PLUGIN_TYPE_ROOT = '#pluginTypeRoot';

    // Not private for easier unit testing.
    const PLUGIN_ROOT = '#pluginRoot';

    protected string $componentsJsonLocation;

    // Taken from core_component.
    // TODO: We should use the parser to get this.
    protected static $ignoreddirs = [
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

    // Also from core_component.
    protected static $supportsubplugins = ['mod', 'editor', 'tool', 'local'];

    protected array $tree = [];

    public function __construct(private string $moodleroot)
    {
        $this->componentsJsonLocation = $this->moodleroot . '/lib/components.json';
    }

    /**
     * Build a tree of components for resolving component.
     *
     * Also extracts any plugin type root directories found.
     *
     * @throws \Exception
     */
    protected function buildTree(): void
    {
        $componentsJsonData = $this->getCoreComponentsData();

        $this->addComponentsDataToTree($componentsJsonData);

        // Because lib is the root of the core component.
        $this->tree['lib'][self::SUBSYSTEM_ROOT] = null;

        $subpluginsJsonData = $this->getSubpluginData($componentsJsonData);
        foreach ($subpluginsJsonData as $plugin => $data) {
            $this->addComponentsDataToTree($data);

            // We now have a node for the plugin containing the subplugins, so we need to mark
            // this as a plugin root.

            [$type, $name] = explode('_', $plugin, 2);
            $pluginPath = explode('/', $componentsJsonData->plugintypes->{$type});
            $pluginPath[] = $name;
            $currentNode =& $this->tree;
            foreach ($pluginPath as $nodeName) {
                $currentNode =& $currentNode[$nodeName];
            }
            $currentNode[self::PLUGIN_ROOT] = [$type, $name];
        }

        // There's an exception for auth_db.
        $this->addComponentToTree('auth_db', 'auth/db', self::PLUGIN_ROOT);
    }


    /**
     * Add the data from a components.json file to the tree.
     */
    protected function addComponentsDataToTree(object $componentsJsonData): void
    {
        foreach (['plugintypes' => self::PLUGIN_TYPE_ROOT, 'subsystems' => self::SUBSYSTEM_ROOT] as $type => $key) {
            // Subplugins files only have the plugintypes key.
            if (!property_exists($componentsJsonData, $type)) {
                continue;
            }
            foreach ($componentsJsonData->{$type} as $component => $dir) {
                if (is_null($dir)) {
                    continue;
                }

                $this->addComponentToTree($component, $dir, $key);
            }
        }
    }

    /**
     * Get subplugins.json files from plugins with subplugins.
     *
     * @return iterable plugin name => components data
     */
    protected function getSubpluginData(object $componentsJsonData): iterable
    {
        foreach (self::$supportsubplugins as $type) {
            $pluginTypeRoot = $this->moodleroot . '/' . $componentsJsonData->plugintypes->$type;
            foreach (glob($pluginTypeRoot . '/*/db/subplugins.json') as $file) {
                $relativePath = substr($file, strlen($pluginTypeRoot) + 1);
                $plugin = $type . '_' . substr($relativePath, 0, strpos($relativePath, '/'));
                $data = json_decode(file_get_contents($file));
                if (!$data) {
                    continue;
                }
                yield $plugin => $data;
            }
        }
    }

    /**
     * Resolve the component for a path.
     *
     * Note that this returns two non-Moodle component names: core_lib and core_root. These are rewritten to
     * "core" and null respectively by \MoodleAnalyse\Codebase\PathCode::getPathComponent()
     *
     * The core_root one is required to distinguish between a path being in dirroot and not being determined.
     *
     * @todo The core_lib one could do with never being emitted by this method (i.e. rewritten to "core")
     *       and potentially a better solution for root.
     *
     * @param string $path a relative path to a file or directory
     * @return array|null array of [type, name, path within component] or null if it can't be determined
     * @throws \Exception
     */
    public function resolveComponent(string $path): ?array
    {
        if ($this->tree === []) {
            $this->buildTree();
        }

        // Allow resolvedInclude format from PathResolvingVisitor.
        if (str_starts_with($path, '@')) {
            $path = substr($path, 1);
        }

        $path = ltrim($path, '/');

        // If there's nothing left it's dirroot.
        if ($path === '') {
            return ['core', 'root', ''];
        }

        // We can't determine anything if it starts with a variable.
        if (str_starts_with($path, '{')) {
            return null;
        }

        $currentNode =& $this->tree;
        // TODO: Copied from ResolvedIncludeProcessor - refactor.
        // The plus is to prevent double slashes causing empty parts, e.g. /lib//questionlib.php
        // in backup\util\includes\restore_includes.php. These appear to be treated as single slashes
        // in file paths by PHP anyway (which is why the above example works).
        $pathParts = preg_split('#(?<![\'"])/+(?!=[^\'"])#', $path);

        $lastPluginRootValue = null;
        $lastSubsystemValue = null;
        while ($pathItem = array_shift($pathParts)) {

            // Keep track of the last plugin root we visited in case we don't end up in a subplugin.
            if (array_key_exists(self::PLUGIN_ROOT, $currentNode)) {
                $lastPluginRootValue = [...$currentNode[self::PLUGIN_ROOT], implode('/', [$pathItem, ...$pathParts])];
            }

            if (array_key_exists(self::SUBSYSTEM_ROOT, $currentNode)) {
                $lastSubsystemValue = [
                    'core',
                    $currentNode[self::SUBSYSTEM_ROOT],
                    implode('/', [$pathItem, ...$pathParts])
                ];
            }

            // Follow the path directories down the component tree until we get to one we don't know about.
            if (array_key_exists($pathItem, $currentNode)) {
                $currentNode =& $currentNode[$pathItem];

                // We can't use pathItem any more.
                $pathItem = null;

                // The second clause here is to deal with bare component directories, e.g. @/lib/
                if ($pathParts !== [] && !(count($pathParts) === 1 && $pathParts[0] === '')) {
                    continue;
                } else {
                    // It might be just a plugin root or a subsystem directory with no path.
                    if (array_key_exists(self::PLUGIN_ROOT, $currentNode)) {
                        return [...$currentNode[self::PLUGIN_ROOT], ''];
                    }
                    if (array_key_exists(self::SUBSYSTEM_ROOT, $currentNode)) {
                        return ['core', $currentNode[self::SUBSYSTEM_ROOT], ''];
                    }
                }
            }

            $remainingPath = implode('/', $pathParts);
            if (array_key_exists(self::PLUGIN_ROOT, $currentNode)) {
                // Check if we have a substitution that might result in a subplugin root.
                // Unfortunately we don't have enough data to return a coherent dynamic component name:
                // e.g. @/mod/quiz/{$shortsubtype}/{$plugin}/settings.php (not a real one from code)
                // where if $shortsubtype is report the component name is quiz and if accessrule it's
                // quizaccess.
                // Note that we need the starts and ends with calls here to avoid dynamic filenames being rejected,
                // e.g. @/mod/scorm/datamodels/{$scorm->version}lib.php
                if ($pathParts !== [] && str_starts_with($pathParts[0], '{') && str_ends_with($pathParts[0], '}')) {
                    foreach ($currentNode as $dirname => $values) {
                        if ($dirname === self::PLUGIN_ROOT || $dirname === self::PLUGIN_TYPE_ROOT) {
                            continue;
                        }
                        if (array_key_exists(self::PLUGIN_TYPE_ROOT, $values)) {
                            return null;
                        }
                    }
                }

                // There's no point recalculating this.
                return $lastPluginRootValue;
            } elseif (array_key_exists(self::PLUGIN_TYPE_ROOT, $currentNode)) {
                $pluginType = $currentNode[self::PLUGIN_TYPE_ROOT];

                if (!is_null($pathItem) && $this->isValidPluginName($pluginType, $pathItem)) {
                    // If it's a plugin root + a variable, it could be either a plugin directory or a full path.
                    // We use a variable name heuristic here, which may not be 100% accurate but it appears to work
                    // surprisingly well with the core codebase. It's basically "is it a simple variable name and does
                    // it not contain the word 'file'".
                    if ($pathParts === [] && str_starts_with($pathItem, '{')) {
                        if (preg_match('/^\{\\$[a-z0-9]+}$/', $pathItem) && (str_contains($pathItem, 'file') || str_contains($pathItem, 'path'))) {
                            return null;
                        }
                        return [$pluginType, $pathItem, ''];
                    }
                    if (count($pathParts) === 1 && $pathParts[0] == '') {
                        // If there's a trailing slash we have the last part as the empty string, but this
                        // won't result in a trailing slash if it's the only thing there, as there's no insertion
                        // of slashes.
                        $remainingPath = '/';
                    }
                    return [$pluginType, $pathItem, $remainingPath];
                }
            }

            if (!is_null($lastPluginRootValue)) {
                return $lastPluginRootValue;
            }

            if (!is_null($lastSubsystemValue)) {
                return $lastSubsystemValue;
            }

            return ['core', 'root', $path];
        }

        return [];
    }

    /**
     * Add a component to the tree.
     *
     * @param string $component the component
     * @param string $type the type of component, PLUGIN_ROOT, PLUGIN_TYPE_ROOT or SUBSYSTEM_ROOT
     */
    protected function addComponentToTree(string $component, string $componentDirectory, string $type): void
    {
        $currentNode =& $this->tree;
        foreach (explode('/', $componentDirectory) as $dirComponent) {
            if (!array_key_exists($dirComponent, $currentNode)) {
                $currentNode[$dirComponent] = [];
            }
            $currentNode =& $currentNode[$dirComponent];
        }
        if ($type === self::PLUGIN_ROOT) {
            $component = explode('_', $component, 2);
        }
        $currentNode[$type] = $component;
    }

    /**
     * Is the name a valid one for the type of plugin.
     *
     * This method is also a simplified one from core_component.
     *
     * @param string $pluginType
     * @param string $pluginName
     */
    public function isValidPluginName(string $pluginType, string $pluginName): bool
    {
        if (isset(self::$ignoreddirs[$pluginName])) {
            return false;
        }

        // Allow variables where they are the only thing in the name.
        if (preg_match('#^{[^{}]+}$#', $pluginName)) {
            return true;
        }

        if ($pluginType === 'mod') {
            return (bool)preg_match('/^[a-z][a-z0-9]*$/', $pluginName);
        } else {
            return (bool)preg_match('/^[a-z](?:[a-z0-9_](?!__))*[a-z0-9]+$/', $pluginName);
        }
    }

    /**
     * @throws \Exception
     */
    private function getCoreComponentsData(): object
    {
        if (!file_exists($this->componentsJsonLocation)) {
            throw new \Exception("components.json not found at $this->componentsJsonLocation");
        }
        $data = json_decode(file_get_contents($this->componentsJsonLocation));

        if (!$data) {
            throw new \Exception("Unable to read components.json");
        }
        return $data;
    }

}