<?php

declare(strict_types=1);

namespace Rector\Testing;

use Iterator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class StaticFixtureProvider
{
    public static function yieldFilesFromDirectory(string $directory, string $suffix): Iterator
    {
        $fileInfos = self::findFilesInDirectory($directory, $suffix);

        $key = 0;
        foreach ($fileInfos as $fileInfo) {
            yield $key . " " . $fileInfo->getFilename() => [$fileInfo->getPathName()];
            $key++;
        }
    }

    /**
     * @return SplFileInfo[]
     */
    private static function findFilesInDirectory(string $directory, string $suffix): array
    {
        $finder = Finder::create()->in($directory)->files()
            ->name($suffix);

        return iterator_to_array($finder);
    }
}
