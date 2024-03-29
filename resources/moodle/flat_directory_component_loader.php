<?php

/**
 * Loads components from a flat directory structure with frankenstyle-named subdirectories.
 *
 * Note that this doesn't support loading plugins which have subplugins.
 */
class flat_directory_component_loader {

    // Plugin root is on plugins managed by this loader, subplugin root can be ones not managed by it.
    const PLUGIN_ROOT = '@';
    const SUBPLUGIN_ROOT = '!';
    private $pluginsdir;

    private $tree;

    /**
     * @param $pluginsdir
     */
    public function __construct($pluginsdir)
    {
        $this->pluginsdir = $pluginsdir;
        // TODO: Check this is a valid directory (moodle exception may not be available yet?)
    }


    public function fetch_plugins($plugintype) {
        $result = [];
        foreach (scandir($this->pluginsdir) as $dir) {
            if (strpos($dir, $plugintype . '_') === 0) {
                $pluginname = substr($dir, strpos($dir, '_') + 1);
                $result[$pluginname] = realpath($this->pluginsdir . '/' . $dir);
            }
        }
        return $result;
    }

    public function get_path_from_relative($relativepath): ?string {
        global $CFG;

        $node = $this->get_tree();

        $pathparts = explode('/', ltrim($relativepath, '/'));
        $result = null;
        while ($part = array_shift($pathparts)) {

            if (!array_key_exists($part, $node)) {
                if (is_null($result)) {
                    return null;
                }
                $pathparts = array_merge([$result, $part], $pathparts);
                return implode('/', $pathparts);
            }

            // We've reached a plugin root.
            if (array_key_exists(self::PLUGIN_ROOT, $node[$part])) {
                $result = $node[$part][self::PLUGIN_ROOT];
            } elseif (array_key_exists(self::SUBPLUGIN_ROOT, $node[$part])) {
                return null;
            }

            $node = $node[$part];
        }

        return null;
    }

    /**
     * Get the component path.
     *
     * Note that this is only used when core_component is loaded but not initialised (e.g. theme/image.php)
     * and must be fast!
     *
     * If the component loader is managing the component it should return the path without checking whether
     * it exists. Otherwise, null should be returned.
     *
     * @param string $component
     * @param string $relativepath
     * @return string|null
     */
    public function get_component_path(string $component, string $relativepath): ?string {
        if (!is_dir($this->pluginsdir . '/' . $component)) {
            return null;
        }
        if ($relativepath === '') {
            return $this->pluginsdir . '/' . $component;
        }

        return $this->pluginsdir . '/' . $component . '/' . ltrim($relativepath, '/');
    }

    /**
     * Build a tree of paths.
     *
     * @todo This is really slow and should be optimised or cached.
     *
     * @return array
     */
    private function get_tree() {
        global $CFG;

        if (!is_null($this->tree)) {
            return $this->tree;
        }

        // Get from cache.
        $hash = stat($this->pluginsdir)['mtime'];
        $cachefile = $CFG->dataroot . '/flat_directory_component_loader.php';
        if (is_file($cachefile)) {
            $cached = include($cachefile);
            if ($cached->hash === $hash) {
                $this->tree = $cached->tree;
                return $this->tree;
            } else {
                unlink($cachefile);
            }
        }

        $this->tree = [];

        // We need to assume this isn't called while core_component is being initialised, so
        // we have the plugin data available.
        $plugintypes = \core_component::get_plugin_types();

        $directoryiterator = new DirectoryIterator($this->pluginsdir);
        foreach ($directoryiterator as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }

            // Check it's a valid plugin type (this should never fail).
            [$type, $name] = core_component::normalize_component($dir);
            $typepath = $plugintypes[$type];
            if (!(strpos($typepath, $CFG->dirroot) === 0)) {
                // TODO: Throw an exception?
                continue;
            }

            $typepathrelative = substr($typepath, strlen($CFG->dirroot) + 1);

            $dirparts = explode('/', $typepathrelative);
            $dirparts[] = $name;


            $dirpath = $dir->getRealPath();

            $this->add_to_node($this->tree, $dirparts, [self::PLUGIN_ROOT => $dirpath]);

            // Add subplugin roots if there are any.
            $subpluginsfile = "$dirpath/db/subplugins.json";

            if (!is_file($subpluginsfile)) {
                continue;
            }

            $subplugintypes = (array) json_decode(file_get_contents($subpluginsfile))->plugintypes;

            foreach ($subplugintypes as $subplugintype) {
                foreach (core_component::get_plugin_list($subplugintype) as $subplugin => $subplugindir) {
                    $subpluginpathrelative = substr($subplugindir, strlen($CFG->dirroot) + 1);
                    $subplugindirparts = explode('/', $subpluginpathrelative);
                    $this->add_to_node($this->tree, $subplugindirparts, [self::SUBPLUGIN_ROOT => $subplugindir]);
                }
            }

        }

        $cached = (object) ['hash' => $hash, 'tree' => $this->tree];
        file_put_contents($cachefile, '<?php return ' . var_export($cached, true) . ';');

        return $this->tree;
    }

    private function add_to_node(array &$node, array $dirparts, array $attributes) {
        $dir = array_shift($dirparts);
        if (is_null($dir)) {
            return;
        }
        $dirpartscount = count($dirparts);
        if (!array_key_exists($dir, $node)) {
            $node[$dir] = $dirpartscount === 0 ? $attributes: [];
        }
        if ($dirpartscount === 0) {
            return;
        }
        $this->add_to_node($node[$dir], $dirparts, $attributes);
    }
}
