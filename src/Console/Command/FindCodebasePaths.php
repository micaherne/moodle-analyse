<?php

declare(strict_types=1);

namespace MoodleAnalyse\Console\Command;

use MoodleAnalyse\Codebase\Analyse\CodebaseAnalyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The basic analysis command - produces a CSV of all codebase paths found.
 */
class FindCodebasePaths extends Command
{
    private const MOODLE_DIR = 'moodle-dir';
    private const OUTPUT_FILE = 'output-file';

    protected function configure(): void
    {
        $this->setName('find-codebase-paths')
            ->setDescription("Finds references to local codebase in Moodle")
            ->addArgument(self::MOODLE_DIR, InputArgument::REQUIRED, "The directory of the Moodle codebase")
            ->addArgument(self::OUTPUT_FILE, InputArgument::OPTIONAL, "The output CSV file", "codebase-paths.csv");
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moodleDirectory = $input->getArgument(self::MOODLE_DIR);
        if (!is_dir($moodleDirectory)) {
            throw new RuntimeException("Moodle directory $moodleDirectory does not exist");
        }

        $outputFile = $input->getArgument(self::OUTPUT_FILE);
        if (!touch($outputFile)) {
            throw new RuntimeException("Unable to write $outputFile");
        }

        $out = fopen($outputFile, 'w');
        if ($out === false) {
            throw new RuntimeException("Unable to open CSV");
        }

        fputcsv($out, [
            'Relative filename',
            'Component',
            'Path start line',
            'Path end line',
            'Path code',
            'Resolved path',
            'Path component',
            'Component relative path',
            'Parent code',
            'Parent start line',
            'Parent end line',
            'Category',
            'From core component',
            'Assigned from previous path var'
        ]);

        $codebaseAnalyser = new CodebaseAnalyser($moodleDirectory);
        foreach ($codebaseAnalyser->analyseAll() as $fileAnalysis) {
            echo $fileAnalysis->getRelativePath() . "\n";
            foreach ($fileAnalysis->getCodebasePaths() as $codebasePath) {
                $pathCode = $codebasePath->getPathCode();
                $row = [$fileAnalysis->getRelativePath(), $fileAnalysis->getFileComponent(), $pathCode->getPathCodeStartLine(), $pathCode->getPathCodeEndLine(),
                    $pathCode->getPathCode(), $pathCode->getResolvedPath(), $pathCode->getPathComponent(), $pathCode->getPathWithinComponent()];
                $parentCode = $codebasePath->getParentCode();
                if (is_null($parentCode)) {
                    $row = array_merge($row, array_fill(0, 3, ''));
                } else {
                    $row = array_merge($row, [$parentCode->getPathCode(), $parentCode->getPathCodeStartLine(), $parentCode->getPathCodeEndLine()]);
                }

                $row[] = $codebasePath->getPathCategory()?->name;
                $row[] = $codebasePath->isFromCoreComponent() ? "Yes": "No";
                $row[] = $codebasePath->isAssignedFromPreviousPathVariable() ? "Yes": "No";

                if (!is_null($parentCode) && $parentCode instanceof \MoodleAnalyse\Codebase\PathCodeDirrootWrangle) {
                    $row[] = 'WRANGLE!!!: ' . $parentCode->getClassification() . ' ' . ($parentCode->getVariableName() ?? '') . ' ' . ($parentCode->getReplacementString() ?? '');
                } else {
                    $row[] = '';
                }

                fputcsv($out, $row);
            }
        }

        fclose($out);
        return 0;
    }


}