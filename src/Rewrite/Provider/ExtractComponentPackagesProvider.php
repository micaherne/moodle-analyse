<?php

namespace MoodleAnalyse\Rewrite\Provider;

use MoodleAnalyse\Codebase\Analyse\FileAnalysis;
use MoodleAnalyse\Codebase\Analyse\Rewrite\CodebasePathRewriteAnalysis;
use MoodleAnalyse\Codebase\Analyse\Rewrite\FileRewriteAnalysis;
use MoodleAnalyse\Codebase\CodebasePath;
use MoodleAnalyse\Codebase\ComponentResolver;
use MoodleAnalyse\Codebase\PathCategory;
use MoodleAnalyse\Rewrite\GetComponentPathRewrite;
use MoodleAnalyse\Rewrite\GetCorePathRewrite;
use MoodleAnalyse\Rewrite\GetPathFromRelativeRewrite;
use MoodleAnalyse\Rewrite\RelativeDirPathRewrite;
use MoodleAnalyse\Rewrite\Rewrite;
use Psr\Log\LoggerInterface;

/**
 * This class extracts components into their own directories for the following setup:
 *
 * * All components with files which are loaded before core_component is available are in the moodle-core package.
 * * config.php is in the root of the moodle-core package.
 * * There is a front controller which sets $CFG->dirroot to the path to the moodle-core package
 *   (this will be overwritten by config.php on it being loaded).
 * * core_component has been customised with a get_component_path() method which returns the path to the
 *   file within the component given, and a get_path_from_relative() method which returns the absolute path
 *   to a file given the notional relative path from the component root in a standard setup.
 */
class ExtractComponentPackagesProvider
{

    /**
     * @var array<string> The components making up the core package.
     */
    private array $coreComponents;
    private array $componentDirectories;

    /**
     * @param ComponentResolver $componentResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private ComponentResolver $componentResolver,
        private LoggerInterface $logger
    ) {
    }

    public function setCoreComponents(array $coreComponents): void
    {
        $this->coreComponents = $coreComponents;
    }

    public function setComponentDirectories(array $componentDirectories): void
    {
        $this->componentDirectories = $componentDirectories;
    }

    public function analyseFileForRewrite(FileAnalysis $fileAnalysis): FileRewriteAnalysis
    {
        $result = new FileRewriteAnalysis($fileAnalysis);

        foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {
            $result->addCodebasePathRewriteAnalysis($this->analsyeCodebasePathForRewrite($codebasePath));
        }

        return $result;
    }

    private function analsyeCodebasePathForRewrite(CodebasePath $codebasePath): CodebasePathRewriteAnalysis
    {

        $result = new CodebasePathRewriteAnalysis($codebasePath);

        $pathCode = $codebasePath->getPathCode();
        $pathComponent = $pathCode->getPathComponent();

        $sourceIsCore = in_array($codebasePath->getFileComponent(), $this->coreComponents);
        $targetIsCore = in_array(
            $pathComponent,
            $this->coreComponents
        );

        $bothComponentsAreCore = $sourceIsCore && $targetIsCore;

        // Ignore dirroot wrangling.
        // TODO: Rewrite these where possible.
        if ($codebasePath->getPathCategory() === PathCategory::DirRoot) {
            $result->setExplanation("Dirroot wrangling rewrites not implemented yet");
            $result->setWorthInvestigating(true);
            return $result;
        }

        // Rewrite config.php to use $CFG->dirroot. This will need to be set before running any code
        // and will be overwritten by config.php on it being loaded.
        if ($codebasePath->getPathCategory() === PathCategory::Config) {
            if ($sourceIsCore) {
                $result->setExplanation("No need to rewrite config.php as source is core");
                return $result;
            } else {
                $rewrite = new Rewrite(
                    $pathCode->getPathCodeStartFilePos(),
                    $pathCode->getPathCodeEndFilePos(),
                    // Assumption here is that $CFG is always available at the point config.php is loaded (i.e.
                    // we have set it in the front controller, and it's being loaded in the root of the page,
                    // not in a function. This certainly holds for the core Moodle codebase and seems a reasonable
                    // constraint to put on any custom code.
                    '$CFG->dirroot . "/config.php"'
                );
                $result->setRewrite($rewrite);
                $result->setExplanation("Rewrite config.php as source is not core");
                return $result;
            }


        }

        // Any dynamic plugin name, e.g. enrol_{$plugin}, but not components which are entirely dynamic.
        // This tends to be SimpleDynamicFile types (e.g. @/filter/{$filter}/filterlocalsettings.php)
        // but also includes some (uncategorised) plugin directory root things (e.g. @/mod/{$modinstance->modname}).
        if (!is_null($pathComponent) && str_contains($pathComponent, '_{$')) {
            // Note that the second check here means that this will never be core_lib or core_root.
            $rewrite = new GetComponentPathRewrite($pathCode);
            $result->setRewrite($rewrite);
            $result->setExplanation("Rewrite dynamic plugin name to use get_component_path()");
            return $result;

        } elseif ($codebasePath->getPathCategory() === PathCategory::SingleVar) {
            // SingleVar is just a single variable and is exclusively picked up by require / include statements.
            // There is nothing we can do about these and just need to assume that the variable will have been
            // fixed elsewhere if it needs to be. (Many of these are $this->full_path('settings.php') which don't
            // need rewritten anyway.)
            $result->setExplanation("SingleVar rewrites not required / possible");
            return $result;
        } elseif ($codebasePath->getPathCategory() === PathCategory::Suspect) {
            // Suspect is a string that contains $CFG->dirroot but is probably not a path. These are mainly in filter_algebra
            // where they are used in a command sent out to the shell. We can't rewrite these.
            $result->setExplanation("Suspect dirroot use not rewritable");
            $result->setWorthInvestigating(true);
            return $result;
        } elseif ($codebasePath->getPathCategory() === PathCategory::Glob) {
            // There are exactly two globs in the codebase. One in adminlib which just finds all the files in /admin/settings,
            // and one in lib/testing/classes/tests_finder.php which is trying to scan the whole codebase for tests
            // and almost certainly needs manually rewritten.
            // TODO: Fix the tests_finder.php one.
            $result->setExplanation("Glob rewrites can't be done automatically");
            $result->setWorthInvestigating(true);
            return $result;
        } elseif ($codebasePath->getPathCategory() === PathCategory::FullDirRelative) {
            // These are paths that are relative to a variable holding what is probably a path in itself
            // (e.g. {$fullblock}/db/install.php). We can't rewrite these.
            // Again we only know that these are file paths as they are within require / include statements
            // and have to assume that the variable will have been fixed elsewhere if it needs to be.
            // Of the 70 or so of these, all but 9 have either been detected as being a directory from core component
            // or from a variable that has already been dealt with.
            $result->setExplanation("FullDirRelative rewrites not required / possible");
            return $result;
        } elseif ($codebasePath->getPathCategory() === PathCategory::FullRelativePath) {
            $rewrites = new GetPathFromRelativeRewrite($pathCode);
            $result->setRewrite($rewrites);
            $result->setExplanation("Rewrite full relative path to use get_path_from_relative()");
            return $result;
        } elseif ($codebasePath->getPathCategory() === PathCategory::SimpleFile
            || $codebasePath->getPathCategory() === PathCategory::SimpleDir
            || $codebasePath->getPathCategory() === PathCategory::FilenameSubstitution
            || $codebasePath->getPathCategory() === PathCategory::SimpleDynamicFile
            || $codebasePath->getPathCategory() === null
        ) {

            // SimpleDir is complicated sometimes. These are generally specific directories in the codebase
            // e.g. @/lib/editor/tiny but there are a couple where they are plugin roots and are quite likely
            // being scanned for plugin types. (e.g. @/auth in tool_mobile classes/api.php)
            // We just rewrite these with the usual rules (i.e. no intra-core rewrites). If there are any scans for
            // plugins they won't work either way.

            // There are some SimpleDynamicFile types that do not get picked up by the first check above but they're all
            // within a component (e.g. @/mod/chat/gui_ajax/theme/{$theme}/config.php) and they need rewritten to
            // a dynamic path relative to __DIR__ (e.g. __DIR__ . "/gui_ajax/theme/{$theme}/config.php").

            // Unfortunately there are 3 others where the component is not identified, and they should be left
            // alone or rewritten manually. For example, in install.php @/{$config->admin}/environment.xml must not
            // be touched, whereas @/mod/assign/{$shortsubtype}/{$plugin}/settings.php in mod/assign/adminlib.php
            // is going to break and needs rewritten manually. I don't see any way to distinguish these two cases.

            // Similarly, @/{$dir}/{$name}/lib.php in mod/feedback/lib.php is fine, although it would be very
            // difficult to tell the program how to identify that.

            // Don't rewrite anything between core components.
            if ($bothComponentsAreCore) {
                $result->setExplanation("Rewrites between core components not required");
                return $result;
            }

            // Don't rewrite anything where we don't know the target component.
            if (is_null($pathComponent)) {
                $result->setExplanation("Target component is not known");
                $result->setWorthInvestigating(true);
                return $result;
            }

            // Rewrite anything within a non-core component to relative.
            if ($pathComponent === $codebasePath->getFileComponent() && !$targetIsCore) {
                $componentRoot = $this->componentDirectories[$pathComponent];
                $rewrite = new RelativeDirPathRewrite($codebasePath, $componentRoot);
                $result->setRewrite($rewrite);
                $result->setExplanation("Rewrite path inside non-core component to relative");
                return $result;
            }

            // Rewrite root paths to use dirroot and anything else to a get_component_path() call.
            if ($targetIsCore) {
                // TODO: Major bug - we don't know whether $CFG is available!
                //       Also we need to potentially add a global $CFG if it's at the top level of a unit test file.
                //       So it might be better to rewrite these to use get_component_path() as well.
                //       Although I think there's something wonky with using get_component_path() for plugins that
                //       are included in the moodle-core root package so that needs checked.
                // We get the path to the component within the core package and append it to dirroot.

                // If the target component is an actual core one, we use core_component::get_core_path()
                // to avoid having to ensure that $CFG is in scope. Otherwise (if it's a plugin that's in the
                // moodle-core package), we just use get_component_path() as normal
                if ($pathComponent === 'core_root') {
                    $rewrite = new GetCorePathRewrite($pathCode);
                    $result->setRewrite($rewrite);
                    $result->setExplanation("Rewrite path to non-component file in core package to use get_core_path()");
                    return $result;
                } else {
                    $rewrite = new GetComponentPathRewrite($pathCode);
                    $result->setRewrite($rewrite);
                    $result->setExplanation("Rewrite path to non-core component inside core package to use get_component_path()");
                    return $result;
                }
            } else {
                $rewrite = new GetComponentPathRewrite($pathCode);
                $result->setRewrite($rewrite);
                $result->setExplanation("Rewrite path inside non-core component to use get_component_path()");
                return $result;
            }

        } else {
            $result->setExplanation("Unhandled path component");
            $this->logger->alert("Unhandled path component: {$codebasePath->getRelativeFilename()}: {$codebasePath->getPathCode()->getPathCodeStartLine()}");
            $result->setWorthInvestigating(true);
            return $result;
        }


    }
}