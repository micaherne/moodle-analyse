<?php

namespace MoodleAnalyse\SimpleMvc;

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
        $stmts_globals = $parser->parse('<?php global $CFG, $DB, $PAGE, $USER, $SESSION, $OUTPUT, $SITE, $COURSE, $ME, $FULLME, $FULLSCRIPT, $SCRIPT, $PERF, $ACCESSLIB_PRIVATE;');

        // Converts un-namespaced classes to namespaced.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver()); // we will need resolved names

        $requireResolver = new \MoodleAnalyse\Parse\RequireResolverVisitor($pagedir);
        $requireResolver->setReplaceConfig(
            function($node) {
                return [new \PhpParser\Node\Expr\MethodCall(
                    new \PhpParser\Node\Expr\Variable('moodleConfig'),
                    "init"
                )];
            }
        );
        $traverser->addVisitor($requireResolver);

        // hoist functions to top
        $extractFunctions = new \MoodleAnalyse\Parse\ExtractFunctionsVisitor();
        $traverser->addVisitor($extractFunctions);

        // hoist classes to top (e.g. admin/tool/health/index.php)
        $extractClasses = new \MoodleAnalyse\Parse\ExtractClassesVisitor();
        $traverser->addVisitor($extractClasses);

        $stmts = $traverser->traverse($stmts);

        // Build tree for code generation.
        $factory = new BuilderFactory();
        $node = $factory->namespace($namespace)
            ->addStmt($factory->use('\MoodleAnalyse\SimpleMvc\MoodleConfig'))
            ->addStmt($factory->class($straightClassName)
                ->addStmt($factory->method('run')
                    ->makePublic()
                    ->addParam($factory->param('moodleConfig')->setTypeHint('MoodleConfig'))
                    ->addStmts($stmts_globals)
                    ->addStmts($extractClasses->classes)
                    ->addStmts($extractFunctions->functions)
                    ->addStmts($stmts)
                  )
              )
        ->getNode();

        $stmts = array($node);
        $prettyPrinter = new PrettyPrinter\Standard();
        return $prettyPrinter->prettyPrintFile($stmts);

    }
}
