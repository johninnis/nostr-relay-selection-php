<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Service\SelectAuthorRelaysService;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorRelaysContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RealWorldRelaysComplianceTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__.'/../corpus/real-world';

    #[DataProvider('realWorldVectors')]
    public function testRealWorldRelays(string $op, string $pubkey, array $rawEvents, array $expected): void
    {
        $authorPubkey = PublicKey::fromHex($pubkey)
            ?? throw new RuntimeException(sprintf('Invalid pubkey in fixture: %s', $pubkey));
        $events = array_map(
            static fn (array $raw): Event => Event::fromRaw($raw)
                ?? throw new RuntimeException('Invalid event in fixture: '.json_encode($raw)),
            $rawEvents,
        );

        $context = new AuthorRelaysContext($authorPubkey, $events);

        $actual = match ($op) {
            'inbox' => SelectAuthorRelaysService::inbox($context),
            'outbox' => SelectAuthorRelaysService::outbox($context),
            'dm' => SelectAuthorRelaysService::dm($context),
            default => throw new RuntimeException('Unknown op: '.$op),
        };

        $this->assertSame($expected, array_map(static fn (RelayUrl $r) => (string) $r, $actual));
    }

    public static function realWorldVectors(): iterable
    {
        foreach (self::fixtures() as $fixture) {
            foreach (['inbox', 'outbox', 'dm'] as $op) {
                yield "{$fixture['name']} — {$op}" => [
                    $op,
                    $fixture['pubkey'],
                    $fixture['events'],
                    $fixture['expected'][$op],
                ];
            }
        }
    }

    private static function fixtures(): iterable
    {
        $paths = glob(self::FIXTURE_DIR.'/*.json');
        if (false === $paths) {
            throw new RuntimeException('Failed to glob real-world fixtures directory');
        }
        sort($paths);

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            if (false === $contents) {
                throw new RuntimeException(sprintf('Failed to read fixture: %s', $path));
            }
            $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($fixture)) {
                throw new RuntimeException(sprintf('Fixture %s is not a JSON object', $path));
            }
            yield $fixture;
        }
    }
}
