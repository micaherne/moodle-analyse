Moodle Analyse
===

This is a project to use PhpParser to analyse the Moodle codebase (PHP only), and potentially fix issues and improve the codebase by rewriting it.

Potential improvements
---

* Replace relative includes with ones using $CFG->dirroot etc.
* Find class definitions that are not autoloaded, and move them to the classes directory
* Find files that only define functions and rewrite them to be autoloaded classes instead

Exploratory things
---

* Introduce a component relative path method and rewrite includes to use it (so that plugins can be loaded from elsewhere)

Plan
---

* Rewrite paths in place as "canonical" versions and check install and tests still work
* Introduce core_codebase or whatever, and rewrite simple paths to component paths, and check
* Investigate the more complex ones and attempt to rewrite, check
* Introduce "mono" and "composer" modes and attempt to pull some components outside codebase into composer packages (use vendor/installed.php to get the plugin types and locations)
* Pull all components into composer packages and check install and testing