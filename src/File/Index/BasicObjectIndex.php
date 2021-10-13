<?php

namespace MoodleAnalyse\File\Index;

use MoodleAnalyse\File\Analyse\IncludeAnalyser;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;

class BasicObjectIndex implements Index
{

    private string $indexDirectory;
    private CacheInterface $itemCache;
    /** @var CacheInterface[] */
    private array $indexCaches = [];

    private string $indexSubdirectory = 'include';
    /** @var string[]  */
    private array $fieldsToIndex = [];

    /** @var string[] */
    private array $sources = [];

    /**
     * @param string $indexSubdirectory
     * @param string[] $fieldsToIndex
     * @param string[] $sources
     */
    public function __construct(string $indexSubdirectory, array $fieldsToIndex, array $sources)
    {
        $this->indexSubdirectory = $indexSubdirectory;
        $this->fieldsToIndex = $fieldsToIndex;
        $this->sources = $sources;
    }


    public function getSources() {
        return $this->sources;
    }

    public function index($analysis, ?string $sourceClass = null) {
        if (!is_null($sourceClass) && !in_array($sourceClass, $this->sources)) {
            throw new \Exception("Unsupported class $sourceClass");
        }
        $itemIndexes = array_fill_keys(array_keys($this->indexCaches), []);
        foreach ($analysis as $analysisItem) {
            $cacheItem = $this->itemCache->getItem($analysisItem->key);
            $cacheItem->set($analysisItem);
            $this->itemCache->save($cacheItem);

            foreach($itemIndexes as $property => &$items) {
                $key = sha1($analysisItem->$property);
                if (!array_key_exists($key, $items)) {
                    $items[$key] = [];
                }
                $items[$key][] = $analysisItem->key;
            }

        }

        foreach ($itemIndexes as $property => $items) {
            foreach ($items as $key => $values) {
                /** @var CacheItem $cacheItem */
                $cacheItem = $this->indexCaches[$property]->getItem($key);

                // Only write if they're different.
                $existingValue = $cacheItem->get();
                if (is_array($existingValue) && array_intersect($existingValue, $values) === [] && array_intersect($values, $existingValue) === []) {
                    continue;
                }
                $cacheItem->set($values);
                $this->indexCaches[$property]->save($cacheItem);
            }
        }
    }

    /**
     * @param string $indexDirectory
     */
    public function setIndexDirectory(string $indexDirectory): void
    {
        $this->indexDirectory = $indexDirectory . '/' . $this->indexSubdirectory;
        $this->itemCache = new FilesystemAdapter('includeItems', 0, $this->indexDirectory);
        foreach ($this->fieldsToIndex as $property) {
            $this->indexCaches[$property] = new FilesystemAdapter('itemsBy' . ucfirst($property), 0, $this->indexDirectory);
        }
    }


}