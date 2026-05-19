<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Support;

use RuntimeException;

final class CorpusLoader
{
    public static function load(string $filename): array
    {
        $path = __DIR__.'/../corpus/'.$filename;
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new RuntimeException(sprintf('Failed to read corpus file: %s', $path));
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Corpus file %s is not a JSON array', $filename));
        }

        return $decoded;
    }
}
