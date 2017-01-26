<?php

require_once __DIR__ . '/../vendor/autoload.php';



use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\NodeTraverser;
use PhpParser\NodeDumper;
use PhpParser\NodeVisitor\NameResolver;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

if (count($argv) < 2) {
    die("Usage php {$argv[0]} [moodle root]");
}

if (!$moodleroot = realpath($argv[1])) {
    die("Invalid Moodle root");
}

class UsedGlobalsVisitor extends NodeVisitorAbstract
{
    public $globals = [];

    public function leaveNode(Node $node) {
        //echo get_class($node);
        if ($node instanceof \PhpParser\Node\Expr\Variable) {
            if (!is_string($node->name)) {
                return;
            }
            if (strpos($node->name, '_') !== 0 && $node->name == \strtoupper($node->name)) {
                $this->globals[$node->name] = true;
            }
        }
    }
}

class ConfigIncludeVisitor extends NodeVisitorAbstract
{
    public $includes = [];
    public $prettyPrinter;

    public function __construct() {
         $this->prettyPrinter = new PrettyPrinter\Standard();
    }

    public function leaveNode(Node $node) {
        //echo get_class($node);
        if ($node instanceof \PhpParser\Node\Expr\Include_) {
            //if (strpos($node->name, '_') !== 0 && $node->name == \strtoupper($node->name)) {
                // print_r($node); exit;
                if (preg_match('/\bconfig.php\b/', $this->prettyPrinter->prettyPrint([$node->expr]))) {
                    $this->includes[] = $node;
                }
            //}
        }
    }
}

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$prettyPrinter = new PrettyPrinter\Standard();
$nodeDumper = new NodeDumper();
$nodeTraverser = new NodeTraverser();
$nodeTraverser->addVisitor(new NameResolver());
$usedGlobalsVisitor = new UsedGlobalsVisitor();
$configIncludeVisitor = new ConfigIncludeVisitor();
$nodeTraverser->addVisitor($usedGlobalsVisitor);
$nodeTraverser->addVisitor($configIncludeVisitor);

$result = [];

$entryPoints = new \MoodleAnalyse\EntryPoint\EntryPointIterator($moodleroot, __DIR__ . '/whitelist.json');

foreach ($entryPoints as $file) {
    echo "Analysing $file\n";

    $code = file_get_contents($file);
    try {
        $stmts = $parser->parse($code);
        $nodeTraverser->traverse($stmts);
        // $stmts is an array of statement nodes

        $result[$file] = ['globals' => array_keys($usedGlobalsVisitor->globals)];
            $result[$file]['config_includes'] = $configIncludeVisitor->includes;
        $usedGlobalsVisitor->globals = [];
        $configIncludeVisitor->includes = [];
    } catch (\Error $e) {
        echo 'Parse Error: ', $e->getMessage();
    }
}

file_put_contents(__DIR__ . '/parse-output.json', json_encode($result));
