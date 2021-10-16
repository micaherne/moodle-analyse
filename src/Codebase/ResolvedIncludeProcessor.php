<?php
declare(strict_types=1);

namespace MoodleAnalyse\Codebase;

class ResolvedIncludeProcessor
{

    public function categorise(string $resolvedInclude): ?string {
        if (preg_match('#^@/?$#', $resolvedInclude)) {
            return 'dirroot';
        } elseif ($resolvedInclude === '@/config.php') {
            return 'config';
        } elseif (preg_match('#^@[\d\w\-/.]+\.\w+$#', $resolvedInclude)) {
            return 'simple file';
        } elseif (preg_match('#^@[\d\w\-/]+/?$#', $resolvedInclude)) {
            return 'simple dir';
        } elseif (preg_match('#^{[^}{]+}$#', $resolvedInclude)) {
            // e.g. {$somevariable}
            return 'single var';
        } elseif (preg_match('#^@/?{[^}{]+}$#', $resolvedInclude)) {
            // e.g. @/{$somevariable}
            return 'full relative path';
        } elseif (preg_match('#^.+@#', $resolvedInclude)) {
            return 'suspect - embedded @';
        } elseif (preg_match('#\*#', $resolvedInclude)) {
            return 'glob';
        } elseif (preg_match('#^{[^}{]+}/[^}{]+\.\w+$#', $resolvedInclude)) {
            # e.g. {$fullblock}/db/install.php
            return 'fulldir relative';
        } elseif (preg_match('#^@/(([^/]*)/)*[^/}{]*\.\w+$#', $resolvedInclude)) {
            # e.g. {$fullblock}/db/install.php
            return 'simple dynamic file';
        } elseif (preg_match('#^@/[^}{]+/{[^}{]+}\.\w+$#', $resolvedInclude)) {
            # e.g. @/completion/criteria/{$object}.php
            return 'filename substitution';
        } else {
            return null;
        }
    }

}