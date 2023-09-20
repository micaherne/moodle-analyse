<?php

namespace MoodleAnalyse\Rewrite;

use MoodleAnalyse\Codebase\CodebasePath;
use MoodleAnalyse\Codebase\DirrootAnalyser;
use MoodleAnalyse\Codebase\PathCodeDirrootWrangle;

/**
 * A rewrite to replace a dirroot wrangle with a call to core_component::is_inside_codebase or
 * core_component::get_relative_path.
 */
class DirrootWrangleRewrite extends Rewrite
{
    public function __construct(private CodebasePath $codebasePath) {
        $dirroot = $codebasePath->getParentCode();
        if (!($dirroot instanceof PathCodeDirrootWrangle)) {
            throw new \RuntimeException("Parent code is not a PathCodeDirrootWrangle");
        }

        $classification = $dirroot->getClassification();

        $code = null;
        if (($classification & DirrootAnalyser::ABSOLUTE_PATH_IN_CODEBASE) !== 0) {
            $code = '\core_component::is_inside_codebase(' . $dirroot->getVariableName() . ')';

            // No need to care about whether the slash even exists here.

            if (($classification & DirrootAnalyser::NEGATIVE) !== 0) {
                $code = '!' . $code;
            }

        } elseif (($classification & DirrootAnalyser::ABSOLUTE_PATH_TO_RELATIVE) !== 0) {
            // Is the calling code expecting a slash at the start of the path?
            $leadingSlash = (($classification & DirrootAnalyser::NO_SLASH) === 0);

/*            // What is the calling code expecting as a separator? (This is just a guess as the provided path
            // may be a mixture of separators.)
            $separator = substr($this->codebasePath->getPathCode()->getResolvedPath(), 1);
            if ($separator === '{\DIRECTORY_SEPARATOR}') {
                $separatorParam = 'DIRECTORY_SEPARATOR';
            } else {
                $separatorParam = "'{$separator}'";
            }
            $params = [$dirroot->getVariableName()];
            if (!($leadingSlash && $separator === '/')) {
                $params[] = $leadingSlash ? 'true' : 'false';
                if ($separator !== '/') {
                    $params[] = $separatorParam;
                }
            }*/
            $params = [$dirroot->getVariableName()];
            $allowRelative = ($classification & DirrootAnalyser::ALLOW_RELATIVE_PATHS) !== 0;

            if ($leadingSlash) {
                $params[] = 'true';
            } elseif ($allowRelative) {
                // Need to pass false here to allow relative paths parameter third.
                $params[] = 'false';
            }

            if ($allowRelative) {
                $params[] = 'true';
            }

            // Parameters: variable, return leading slash?, allow paths that are already relative?
            $code = '\core_component::get_relative_path(' . implode(", ", $params) . ')';
        } elseif (($classification & DirrootAnalyser::REPLACE_WITH_STRING) !== 0) {
            // Don't bother with these. The only instances are in lib/upgradelib.php and we don't want to
            // mess with that. (Also the code looks weird as it's looking for '$CFG->dirroot' as a string
            // literal and then trying to replace the actual wwwroot value with that same literal.)
            throw new \RuntimeException("Do not create this class where the classification is REPLACE_WITH_STRING");
        } else {
            throw new \RuntimeException("Unknown classification");
        }

        parent::__construct(
            $dirroot->getPathCodeStartFilePos(),
            $dirroot->getPathCodeEndFilePos(),
            $code
        );
    }


}