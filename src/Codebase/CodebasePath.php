<?php

namespace MoodleAnalyse\Codebase;

use PhpParser\Node;
use PhpParser\Parser;

class CodebasePath
{

    private Parser $parser;

    /**
     * @param Parser $parser
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }


    public function normalisedPathToNode(string $path): Node {
        if (!str_starts_with($path, '@')) {
            throw new \Exception("Normalised path must start with @");
        }

        if (str_starts_with($path, '@/lib/')) {
            $path = '{$CFG->libdir}/' . substr($path, 6);
        } elseif (str_starts_with($path, '@/admin/')) {
            $path = '{$CFG->dirroot}/{$CFG->admin}/' . substr($path, 8);
        } else {
            $path = '{$CFG->dirroot}/'. substr($path, 2);
        }

        $matches = [];

        // THIS IS UNFINISHED AND DOESN'T WORK!

    }

}