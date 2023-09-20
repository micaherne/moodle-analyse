Updates to core_component for rewritten code
===

This document describes the new methods on core_components that need to be added for code rewritten by the extract-component-packages command to work.

## Background

The extract-component-packages command moves code from the core codebase into separate packages. This means that the code is no longer in the same directory as the rest of Moodle, and so any code that references it by absolute path will no longer work.

The intended setup is that there is a front-controller script to which all requests are routed. This does some setup to enable the discovery of components outside of dirroot - which is still the root of the "core package" that consists of all core components and files that are in no component, such as index.php. It then forwards the request to the appropriate "page controller" script.

## Component loader

The component loader is a class that is responsible for loading components outside of dirroot. It is a singleton, and is accessed via the protected `core_component::get_component_loader()` method.

The current implementation of this relies on a global function moodle_get_component_loader() being defined (although this is not ideal and could be changed in the future).

At the end of the fetch_plugins() method, the component loader is asked for any extra plugins of that type that are available.

The Moodle knowledge of the component loader is very limited by design. Its only requirement is that it knows how to find the directories of components it manages, and identify their component type and name.

## New methods

There are some new static methods required to replace code that is expecting the entire codebase to be underneath $CFG->dirroot.

Note that these methods must not assume that the subpath actually exists - many parts of Moodle construct paths to files that do not exist, and then check for their existence later.

### `get_component_path($component, $subpath)`

This is a replacement for the use of absolute paths, for example in require_once calls. It returns the absolute path to the given subpath within the component, or null if the component is not found.

For example, `require_once($CFG->dirroot . '/mod/assign/locallib.php')` would become `require_once(core_component::get_component_path('mod_assign', 'locallib.php'))`.

### `in_codebase($absolutepath)`

Returns true if the given path is in the codebase, false otherwise.

Again this should not assume that the path actually exists.

It is a replacement for the following construct:

* strpos($path, $CFG->dirroot) === 0

### `get_core_path($subpath)`

This is a helper method for getting paths that do not exist in a component.

It is equivalent to prefixing the path with $CFG->dirroot, but does not require the $CFG global to be in scope at the point at which it is called.

This is a replacement for the following construct:

* $CFG->dirroot . $path (with alterations for leading slashes if required)


### `get_relative_path($path, $leadingslash = false, $allowalreadyrelative = false)`

This is in general a replacement for the following constructs:

* substr($path, strlen($CFG->dirroot))
* substr($path, strlen($CFG->dirroot) + 1)
* str_replace($CFG->dirroot, '', $path)
* str_replace($CFG->dirroot . '/', '', $path)

It returns the relative path to where the file or directory would be if it were in the standard location within Moodle (i.e. the path in the URL)

The `$leadingslash` parameter controls whether the returned path starts with a slash or not. This would be true for examples 1 & 3 above, and false for examples 2 & 4.

The `$allowalreadyrelative` parameter controls whether the method will return the path unchanged if it is already relative. This would be true for examples 3 & 4 above.

### `get_path_from_relative($relativepath)`

Given a relative path (in the standard Moodle layout), this returns the absolute path to the file or directory.

This is a replacement for the following constructs:

* $CFG->dirroot . $relativepath
* $CFG->dirroot . '/' . $relativepath

## Note on scope

Clearly there is some code in Moodle which runs before core_component has been initialised (or even loaded) so none of the above methods can be used. To ensure that this code continues to work, it must be entirely in the moodle-core package, where no internal paths are rewritten (including any functions etc that it uses).

This includes the following:

* All core components: scripts like install.php, lib/javascript.php etc
* tool_phpunit and tool_behat: the bootstrap.php file
* Various cachestore plugins: a few of these are loaded very early in the bootstrap process