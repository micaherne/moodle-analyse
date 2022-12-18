Set up Moodle to use external plugins
===
With a bit of work this library can be used to set up a Moodle site which will load plugins from a directory outside of the Moodle codebase.

Get the Moodle codebase
---
Clone https://github.com/micaherne/moodle.git  and check out the [master-rewrite-manual-changes](https://github.com/micaherne/moodle/tree/master-rewrite-manual-changes) branch. This is only three commits which:

* add a few new methods to core_component:
>* get_component_loader(): enables an external class to be used to load plugins.
>* get_path_from_relative(): takes a Moodle path (e.g. 'mod/assign/feedback/comments/lib.php) and returns the path to the actual file / directory
>* get_component_path(): takes a frankenstyle component name and the relative path within the component, and returns the path to the actual file / directory
* change the phpunit.xml creation to use absolute paths if they are outside of dirroot, so that unit tests can still be run
* rewrite xmldb_structure.php to use get_path_from_relative() to prevent install failing

Rewrite the Moodle codebase to use new component methods
---
Run `bin/moodle-analyse rewrite-for-plugin-extraction -vvv "/path/to/moodle"`

This will take a while and should result in something similar to [this](https://github.com/moodle/moodle/commit/2b3bfaa3c45d4b5206d92d4206c50e4b31472833), where paths within the codebase have been rewritten to use the new component methods where necessary:
* paths where there is some kind of access to plugins based on a variable (e.g. file_exists("$CFG->dirroot/blocks/$blockname/block_$blockname.php"))
* paths which are completely relative to dirroot (e.g. $CFG->dirroot.$location)
* simple paths where the target component is different from the source one (e.g. require_once($CFG->dirroot . '/mod/quiz/locallib.php'))

Create a config file with a plugin loader
---
* Create a config.php file with the correct settings for Moodle. (The plugin loader has to be available before setup.php is called so currently the only place to do this is in config.php, and running install.php isn't yet supported - see later)
* Create a directory to hold your plugins.
* Optionally grab resources/flat_directory_component_loader.php from this library and put it somewhere near your Moodle code.
* Add the following code to your config.php

      require_once '/some/path/flat_directory_component_loader.php';

      function moodle_get_component_loader() {
          return new flat_directory_component_loader ('/path/to/your/plugin/directory');
      } 

Extract plugins into your directory
---
Run `bin/moodle-analyse extract-all-plugins --delete  -vvv "/path/to/moodle"` (note that this will *remove* the plugins from your Moodle codebase)

This will copy the plugin (without any subplugins) and rewrite any links outside the plugin to use the new component methods.

(This does not yet support plugins with CLI scripts, web scripts, non-modular Javascripts - see later for explanation)

Install Moodle
---
Run the install_database.php CLI script, which should install correctly with all of the externalised plugins available.

Notes
---
* This currently only supports externalising plugins which have no scripts that are intended to be directly run through either the web or the command line. This is because these have to load config.php which is no longer in the same place relative to the file. We _could_ rewrite these to absolute paths but this would be a bad solution as the code would not be in any way portable. It would be much preferable to introduce front controllers (for web and CLI) and generate controller classes / functions for these scripts.
* A front controller would also be essential for supporting things like Javascript source maps where a URL to the source map is required but this is not possible if the plugin providing the source map is outside of the server web root.
* Use of front controllers would also enable the component loader classes to be configured and initialised completely outside of Moodle.
* There is not currently a way to install a third party plugin externally, although it shouldn't be difficult to adapt the existing code to provide a command to rewrite these.
* This is purely a proof-of-concept and not recommended to be used in production.
* There are still various places in the Moodle code that won't work correctly with this setup. We can get a comprehensive (I think) list of these by running `bin/moodle-analyse find-codebase-paths "/path/to/a/plain/moodle/codebase" paths.csv` and filtering the CSV to category of DirRoot or no category. These would need to be manually investigated and fixed but dirroot wrangling seems to be mainly for these cases:
>* Checking whether a file is in the codebase (e.g. strpos($filepath, $CFG->dirroot) === 0) 
>* Converting a file path to a relative web path (e.g. str_replace($CFG->dirroot, '', $filepath))
>* Obscuring the path in an error message (e.g. str_replace($CFG->dirroot, '[dirroot]', $filepath))