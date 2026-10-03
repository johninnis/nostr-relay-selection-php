<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use Innis\Nostr\RelaySelection\Tests\Support\CorpusLoader;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CorpusDigestTest extends TestCase
{
    private const string DIGEST_FILE = 'corpus.sha256';

    public function testEveryCorpusFileMatchesTheDigestPinnedWithTheTypeScriptPort(): void
    {
        $pinned = file_get_contents(CorpusLoader::DIRECTORY.self::DIGEST_FILE) ?: throw new RuntimeException('Missing '.self::DIGEST_FILE);

        $this->assertSame(trim($pinned), self::corpusDigest());
    }

    private static function corpusDigest(): string
    {
        $root = realpath(CorpusLoader::DIRECTORY) ?: throw new RuntimeException('Missing corpus directory');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        $paths = array_values(array_filter($paths, static fn (string $path): bool => self::DIGEST_FILE !== $path));
        sort($paths, SORT_STRING);
        $lines = array_map(
            static fn (string $path): string => $path."\n".(hash_file('sha256', $root.'/'.$path) ?: throw new RuntimeException('Unreadable '.$path))."\n",
            $paths,
        );

        return hash('sha256', implode('', $lines));
    }
}
