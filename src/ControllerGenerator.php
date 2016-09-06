<?php

namespace MoodleAnalyse;

use PhpParser\ParserFactory;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Generate a controller class given a Moodle script.
 */
class ControllerGenerator {

    private $moodleroot;

    public function __construct($moodleroot) {
        $this->moodleroot = $moodleroot;
    }

    /**
     * Generate the code for a controller class.
     *
     * @param $classname string the namespaced name of the class to generate
     * @param $moodlepage string the contents of the Moodle script
     * @param $pagedir string the directory (relative to Moodle root) of the page.
     *
     */
    public function generate($classname, $moodlepage, $pagedir) {
        $classnameParts = explode('\\', $classname);
        $straightClassName = array_pop($classnameParts);
        $namespace = implode('\\', $classnameParts);
        // Remove backslashes.
        $namespace = trim($namespace, '\\');

        $code = $moodlepage;

        // Parse code with PHP parser.
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $stmts = $parser->parse($code);

        // Get node for global declarations.
        $stmts_globals = $parser->parse('<?php global $CFG, $DB, $PAGE, $USER, $SESSION, $OUTPUT;');

        // Converts un-namespaced classes to namespaced.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver()); // we will need resolved names
        $traverser->addVisitor(new RequireResolverVisitor($pagedir));

        $stmts = $traverser->traverse($stmts);

        // Build tree for code generation.
        $factory = new BuilderFactory();
        $node = $factory->namespace($namespace)
            ->addStmt($factory->class($straightClassName)
                ->addStmt($factory->method('run')
                    ->makePublic()
                    ->addStmts($stmts_globals)
                    ->addStmts($stmts)
                  )
              )
        ->getNode();

        $stmts = array($node);
        $prettyPrinter = new PrettyPrinter\Standard();
        return $prettyPrinter->prettyPrintFile($stmts);

    }
}
