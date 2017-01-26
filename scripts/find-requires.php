<?php

// $formatfile in question/import.php is relative
// $formaction in user/action_redir.php is relative
// lib\\editor\\tinymce\\plugins\\spellchecker\\includes\\general.php is not an entry point
require_once __DIR__ . '/../vendor/autoload.php';

use PhpParser\ParserFactory;

if (count($argv) < 2) {
    die("Usage php {$argv[0]} [moodle root]");
}

if (!$moodleroot = realpath($argv[1])) {
    die("Invalid Moodle root");
}

class IncludeTypeVisitor extends \PhpParser\NodeVisitorAbstract {

    public $includes = [];
    public $pretty;
    public $file;
    public $stats;

    public function __construct(\PhpParser\PrettyPrinter\Standard $pretty) {
        $this->pretty = $pretty;
        $this->stats = array_fill_keys(['all', 'dir', 'variable'], 0);
    }

    public function enterNode(\PhpParser\Node $node) {
        if ($node instanceof \PhpParser\Node\Expr\Include_) {
            $include = $this->pretty->prettyPrint([$node->expr]);
            $include = rtrim($include, ';'); // pretty printed line end
            if (strpos($include, '$CFG') !== false) {
                return;
            }
            if (preg_match('/\bconfig.php\b/', $include)) {
                return;
            }
            if (!isset($this->includes[$include])) {
                $this->includes[$include] = [];
                $this->stats['all']++;
                if (strpos($include, '$') !== false) {
                    $this->stats['variable']++;
                }
                if (strpos($include, '__DIR__') !== false) {
                    $this->stats['dir']++;
                }
            }
            $this->includes[$include][$this->file] = 1;
        }
    }

}

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$pretty = new \PhpParser\PrettyPrinter\Standard();
$visitor = new IncludeTypeVisitor($pretty);
$nodeTraverser = new \PhpParser\NodeTraverser();
$nodeTraverser->addVisitor($visitor);

$entryPoints = new \MoodleAnalyse\EntryPoint\EntryPointIterator($moodleroot, __DIR__ . '/whitelist.json');

foreach ($entryPoints as $file) {
    if ($file === realpath($moodleroot . '/install.php')) {
        continue;
    }
    echo "Checking file $file\n";
    $visitor->file = $file;
    $code = file_get_contents($file);
    $stmts = $parser->parse($code);
    $nodeTraverser->traverse($stmts);
}

file_put_contents('relative-requires.json', json_encode($visitor->includes));

print_r($visitor->stats);
