<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorRelaySelector;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorRelaysContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RealWorldRelaysComplianceTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__.'/../corpus/real-world';

    /**
     * @param array<array-key, mixed> $rawEvents
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('realWorldVectors')]
    public function testRealWorldRelays(string $op, string $pubkey, array $rawEvents, array $expected): void
    {
        $authorPubkey = PublicKey::tryFromHex($pubkey)
            ?? throw new RuntimeException(sprintf('Invalid pubkey in fixture: %s', $pubkey));
        $events = array_map(
            static fn (mixed $raw): Event => Event::tryFromRaw($raw)
                ?? throw new RuntimeException('Invalid event in fixture: '.json_encode($raw)),
            self::expectList($rawEvents),
        );

        $context = new AuthorRelaysContext($authorPubkey, $events);

        $actual = match ($op) {
            'inbox' => AuthorRelaySelector::inbox($context),
            'outbox' => AuthorRelaySelector::outbox($context),
            'dm' => AuthorRelaySelector::dm($context),
            default => throw new RuntimeException('Unknown op: '.$op),
        };

        $this->assertSame($expected, array_map(static fn (RelayUrl $r) => (string) $r, $actual));
    }

    private static function expectString(mixed $raw): string
    {
        if (!is_string($raw)) {
            throw new RuntimeException('Expected a string in fixture, got '.get_debug_type($raw));
        }

        return $raw;
    }

    /**
     * @return list<mixed>
     */
    private static function expectList(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new RuntimeException('Expected a list in fixture, got '.get_debug_type($raw));
        }

        return array_values($raw);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function realWorldVectors(): iterable
    {
        foreach (self::fixtures() as $fixture) {
            $expectedByOp = $fixture['expected'] ?? null;
            if (!is_array($expectedByOp)) {
                throw new RuntimeException('Fixture is missing its expected results');
            }

            foreach (['inbox', 'outbox', 'dm'] as $op) {
                $expected = self::expectList($expectedByOp[$op] ?? null);
                yield self::expectString($fixture['name']).' — '.$op => [
                    $op,
                    self::expectString($fixture['pubkey']),
                    self::expectList($fixture['events']),
                    $expected,
                ];
            }
        }
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
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
            $named = [];
            foreach ($fixture as $key => $value) {
                $named[(string) $key] = $value;
            }

            yield $named;
        }
    }
}
