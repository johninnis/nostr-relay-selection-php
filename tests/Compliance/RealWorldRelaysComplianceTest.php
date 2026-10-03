<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;
use Innis\Nostr\RelaySelection\Tests\Support\CorpusLoader;
use Innis\Nostr\RelaySelection\Tests\Support\CorpusValues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RealWorldRelaysComplianceTest extends TestCase
{
    #[DataProvider('realWorldVectors')]
    public function testRealWorldRelays(RelayRole $role, mixed $pubkey, mixed $events, mixed $expected): void
    {
        $directory = RelayDirectory::fromEvents(CorpusValues::events($events));

        $this->assertSame($expected, $directory->relaysOf(CorpusValues::pubkey($pubkey), $role)->toStrings());
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function realWorldVectors(): iterable
    {
        $paths = glob(CorpusLoader::DIRECTORY.'real-world/*.json') ?: throw new RuntimeException('No real-world fixtures found');
        sort($paths);
        foreach ($paths as $path) {
            $fixture = CorpusValues::object(CorpusLoader::loadJson('real-world/'.basename($path)));
            $expected = CorpusValues::object($fixture['expected']);
            foreach ([RelayRole::Inbox, RelayRole::Outbox, RelayRole::Dm] as $role) {
                yield CorpusValues::string($fixture['name']).' — '.$role->value => [$role, $fixture['pubkey'], $fixture['events'], $expected[$role->value]];
            }
        }
    }
}
