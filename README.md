Moodle Analyse
===

This is a project to use PhpParser to analyse the Moodle codebase (PHP only), particularly for internal links within the codebase.

This can be used to create a Moodle installation with plugins loaded from outside of dirroot - see the [documentation page](docs/moodle-external-plugins.md) for more details.

Background
---
It could be argued that the current Moodle codebase is a "legacy" codebase in the sense that it does not make use of many standard modern programming methods and patterns (e.g. https://www.packtpub.com/product/modernizing-legacy-applications-in-php/9781787124707).

For example:

* the lack of a front controller forces the entire codebase to be hosted in the web root of a server, including the configuration file, arguably a security concern
* the codebase relies heavily on global variables, which may be helped if dependency injection were available

A good start to improving this would be to make the codebase more properly modular, and be able to load plugins (or even core components) from outside the server root. The main thing currently preventing this is the profusion of links between components, in particular requires.

This library is an attempt to find these, and ideally rewrite them to support a more modular structure.

Introduction
---
The main components in this library are the PathFindingVisitor and PathResolvingVisitor which can be used with [PHP Parser](https://github.com/nikic/PHP-Parser) to identify intra-codebase paths and parse them into a simple form (e.g. @/mod/assign/index.php, or @/enrol/{$plugin}/lib.php) which can be used for rewriting.

(Note: the PathResolvingVisitor is a fairly chaotic piece of code - I wrote it a long time ago during covid lockdown and can barely remember how it works but it has a large test suite :) )

