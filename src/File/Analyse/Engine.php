<?php
declare(strict_types=1);

namespace MoodleAnalyse\File\Analyse;

use Exception;
use MoodleAnalyse\Codebase\ComponentIdentifier;
use MoodleAnalyse\File\FileFinder;
use MoodleAnalyse\File\Index\BasicObjectIndex;
use MoodleAnalyse\File\Index\Index;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\SplFileInfo;

class Engine
{
    private string $moodleroot;
    private string $indexDirectory;
    private FileFinder $fileFinder;
    private Lexer $lexer;
    private Parser $parser;
    private NodeTraverser $traverser;
    private FunctionDefinitionAnalyser $functionDefinitionAnalyser;
    private IncludeAnalyser $includeAnalyser;
    private ComponentIdentifier $componentIdentifier;
    private array $analysers = [];

    /** @var Index[] */
    private array $indexes = [];

    /**
     * @param string $moodleroot
     * @param string $indexDirectory
     */
    public function __construct(string $moodleroot, string $indexDirectory)
    {
        $this->moodleroot = $moodleroot;
        $this->indexDirectory = $indexDirectory;

        $this->fileFinder = new FileFinder($this->moodleroot);
        $this->setUpParser();
    }

    private function setUpParser() {

        $this->lexer = new Lexer(['usedAttributes' => ['startFilePos', 'endFilePos']]);
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $this->lexer);
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor(new ParentConnectingVisitor());

        $this->functionDefinitionAnalyser = new FunctionDefinitionAnalyser();
        $this->includeAnalyser = new IncludeAnalyser();
        $this->componentIdentifier = new ComponentIdentifier($this->moodleroot);

        /**
         * @var FileAnalyser[]
         */
        $this->analysers = [$this->functionDefinitionAnalyser, $this->includeAnalyser];

        $this->indexes = [];

        /** @var FileAnalyser $analyser */
        foreach ($this->analysers as $analyser) {
            if ($analyser instanceof UsesComponentIdentifier) {
                $analyser->setComponentIdentifier($this->componentIdentifier);
            }
            foreach ($analyser->getNodeVisitors() as $visitor) {
                $this->traverser->addVisitor($visitor);
            }
            foreach ($analyser->getIndexes() as $index) {
                $index->setIndexDirectory($this->indexDirectory);
                $this->indexes[] = $index;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function execute() {
        $requireCounts = [];

        $parsedFilesCount = 0;

        /** @var SplFileInfo $file */
        foreach ($this->fileFinder->getFileIterator() as $file) {
            echo sprintf("Analysing file: %s\n", $file->getRelativePathname());
            $fileContents = $file->getContents();
            $component = $this->componentIdentifier->fileComponent($file->getRelativePathname());

            $fileDetails = new FileDetails($file, $fileContents, $component);

            foreach ($this->analysers as $analyser) {
                $analyser->setFileDetails($fileDetails);
            }

            $nodes = $this->parser->parse($fileContents);
            $this->traverser->traverse($nodes);

            foreach ($this->analysers as $analyser) {
                $analysis = $analyser->getAnalysis();
                foreach ($this->indexes as $index) {
                    if (in_array(get_class($analyser), $index->getSources())) {
                        $index->index($analysis, get_class($analyser));
                    }
                }

                // Get rid of this - it's just a test thing.
                if ($analyser instanceof IncludeAnalyser) {
                    foreach ($analysis as $include) {
                        $resolved = $include->resolved;
                        if (!array_key_exists($resolved, $requireCounts)) {
                            $requireCounts[$resolved] = 0;
                        }
                        $requireCounts[$resolved]++;
                    }
                }
            }

            // Recreate the parser etc. every so often to hopefully prevent running out of memory.
            // See https://github.com/nikic/PHP-Parser/blob/master/doc/component/Performance.markdown
            if ($parsedFilesCount++ % 100 === 0) {
                echo sprintf("Parsed %d files\n", $parsedFilesCount);
                $this->setUpParser();
            }
        }

        // rsort($requireCounts);
        $out = fopen($this->indexDirectory . '/require_counts.csv', 'w');
        foreach($requireCounts as $target => $count) {
            fputcsv($out, [$target, $count]);
        }
        fclose($out);
    }

    public function addIndex(BasicObjectIndex $index)
    {
        $index->setIndexDirectory($this->indexDirectory);
        $this->indexes[] = $index;
    }


}