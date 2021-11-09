Moodle Analyse
===

This is a project to use PhpParser to rewrite the Moodle codebase (PHP only) to enable it to use modern features such as:

* front controller / page controller classes
* dependency injection
* composer

Background
---
It could be argued that the current Moodle codebase is a "legacy" codebase in the sense that it does not make use of many standard modern programming methods and patterns (e.g. https://www.packtpub.com/product/modernizing-legacy-applications-in-php/9781787124707).

For example:

* the lack of a front controller forces the entire codebase to be hosted in the web root of a server, including the configuration file, arguably a security concern
* the codebase relies heavily on global variables, which may be helped if dependency injection were available

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