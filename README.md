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