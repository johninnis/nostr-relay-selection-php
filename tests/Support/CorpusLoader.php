<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Support;

use RuntimeException;

final class CorpusLoader
{
    public const string DIRECTORY = __DIR__.'/../corpus/';

    public static function loadJson(string $path): mixed
    {
        $contents = file_get_contents(self::DIRECTORY.$path);
        if (false === $contents) {
            throw new RuntimeException(sprintf('Failed to read corpus file: %s', $path));
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function load(string $filename): array
    {
        return array_map(CorpusValues::object(...), CorpusValues::list(self::loadJson($filename)));
    }
}
