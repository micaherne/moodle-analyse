<?php

declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

enum PathCategory
{
    /** The Moodle root, with or without a leading slash or PATH_SEPARATOR */
    case DirRoot;

    /** config.php in the Moodle root */
    case Config;

    /** A well-defined file path with no variables */
    case SimpleFile;

    /** A well-defined directory path with no variables */
    case SimpleDir;

    /** A single variable e.g. ({$pathtofile}) */
    case SingleVar;

    /** A single variable with a path from the Moodle root (e.g. @/{$pathtofile})*/
    case FullRelativePath;

    /** Something that probably isn't a Moodle path, generally a string that contains $CFG->dirroot */
    case Suspect;

    /** A glob without variables */
    case Glob;

    /** A file path relative to a directory in a variable (e.g. {$fullblock}/db/install.php) */
    case FullDirRelative;

    /** A file path with a simple variable substitution in it */
    case SimpleDynamicFile;

    /** A well-defined path with a filename substitution only (e.g.  */
    case FilenameSubstitution;
}
