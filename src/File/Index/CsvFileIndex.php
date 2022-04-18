<?php

declare(strict_types=1);

namespace MoodleAnalyse\File\Index;

use Exception;

class CsvFileIndex implements Index
{

    private string $indexDirectory;

    /**
     * @param string $indexName
     * @param string[] $fieldsToIndex
     * @param string[] $sources
     */
    public function __construct(private string $indexName, private array $fieldsToIndex, private array $sources)
    {

    }

    public function getSources()
    {
        return $this->sources;
    }

    public function index($analysis, ?string $sourceClass = null)
    {
        if (!is_null($sourceClass) && !in_array($sourceClass, $this->sources)) {
            throw new Exception("Unsupported class $sourceClass");
        }
        $csvFile = $this->get_csv_file_path();
        if (!file_exists(dirname($csvFile))) {
            mkdir(dirname($csvFile), 0777, true);
        }
        $csv = fopen($csvFile, 'a');
        $data = (array) $analysis;
        foreach ($data as $record) {
            $record = (array) $record;
            // Simple way to filter the array by keys.
            $record = array_intersect_key($record, array_flip($this->fieldsToIndex));
            fputcsv($csv, $record);
        }

        fclose($csv);
    }

    /**
     * @inheritDoc
     */
    public function setIndexDirectory(string $indexDirectory): void
    {
        $this->indexDirectory = $indexDirectory;
    }

    /**
     * @return string
     */
    private function get_csv_file_path(): string
    {
        return $this->indexDirectory . '/' . $this->indexName . '.csv';
    }

    public function reset()
    {
        $csvFilePath = $this->get_csv_file_path();
        if (file_exists($csvFilePath)) {
            unlink($csvFilePath);
        }
        $csv = fopen($csvFilePath, 'w');
        fputcsv($csv, $this->fieldsToIndex);
        fclose($csv);
    }
}