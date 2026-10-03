<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Failure\NoDmRelaysFailure;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\FilterPatternClassifier;
use Innis\Nostr\RelaySelection\Domain\Service\PublishRouter;
use Innis\Nostr\RelaySelection\Domain\Service\ReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\RecipientsWithoutInboxFinder;
use Innis\Nostr\RelaySelection\Domain\Service\RelayHintSelector;
use Innis\Nostr\RelaySelection\Domain\Service\ZapRequestRelaySelector;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\AuthorReadPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PerRecipientCap;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PositiveCount;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PublishPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\ReadPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\Redundancy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\AuthorReadRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\PublishRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\ReadRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayHintTarget;
use Innis\Nostr\RelaySelection\Tests\Support\CorpusLoader;
use Innis\Nostr\RelaySelection\Tests\Support\CorpusValues;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorpusComplianceTest extends TestCase
{
    #[DataProvider('normaliseUrlVectors')]
    public function testNormaliseUrl(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, self::normalise($input));
    }

    #[DataProvider('normaliseUrlIdempotencyVectors')]
    public function testNormaliseUrlIsIdempotent(string $canonical): void
    {
        $this->assertSame($canonical, self::normalise($canonical));
    }

    #[DataProvider('buildRelaySetVectors')]
    public function testBuildRelaySet(mixed $input, mixed $expected): void
    {
        $sets = array_map(static fn (mixed $source): RelaySet => RelaySet::fromStrings(CorpusValues::list($source)), CorpusValues::list($input));

        $this->assertSame($expected, new RelaySet()->union(...$sets)->toStrings());
    }

    #[DataProvider('extractRelayUrlsVectors')]
    public function testExtractRelayUrls(mixed $role, mixed $tags, mixed $expected): void
    {
        $this->assertSame($expected, CorpusValues::role($role)->extract(CorpusValues::tags($tags))->toStrings());
    }

    #[DataProvider('relaysOfVectors')]
    public function testRelaysOf(mixed $pubkey, mixed $role, mixed $directory, mixed $expected): void
    {
        $relays = CorpusValues::directory($directory)->relaysOf(CorpusValues::pubkey($pubkey), CorpusValues::role($role));

        $this->assertSame($expected, $relays->toStrings());
    }

    #[DataProvider('routePublishVectors')]
    public function testRoutePublish(mixed $event, mixed $directory, mixed $policy, mixed $expected): void
    {
        $raw = CorpusValues::object($policy);
        if (CorpusValues::isInvalidArgument($expected)) {
            $this->expectException(InvalidArgumentException::class);
        }
        $route = PublishRouter::route(CorpusValues::event($event), CorpusValues::directory($directory), new PublishPolicy(
            CorpusValues::relays($raw['privateContentRelays']),
            CorpusValues::relays($raw['indexerRelays']),
            CorpusValues::relays($raw['groupRelays']),
            ...(isset($raw['perRecipientCap']) ? ['perRecipientCap' => self::perRecipientCapOf($raw['perRecipientCap'])] : []),
        ));

        $this->assertSame($expected, self::outcomeOf($route));
    }

    #[DataProvider('routeReadVectors')]
    public function testRouteRead(mixed $filters, mixed $directory, mixed $policy, mixed $expected): void
    {
        $raw = CorpusValues::object($policy);
        $route = ReadRouter::route(
            CorpusValues::filters($filters),
            CorpusValues::directory($directory),
            new ReadPolicy(CorpusValues::pubkey($raw['userPubkey']), CorpusValues::relays($raw['callerRelays'])),
        );

        $this->assertSame($expected, self::outcomeOf($route));
    }

    #[DataProvider('routeAuthorReadsVectors')]
    public function testRouteAuthorReads(mixed $authors, mixed $directory, mixed $policy, mixed $expected): void
    {
        $raw = CorpusValues::object($policy);
        if (CorpusValues::isInvalidArgument($expected)) {
            $this->expectException(InvalidArgumentException::class);
        }
        $routes = AuthorReadRouter::route(CorpusValues::pubkeys($authors), CorpusValues::directory($directory), new AuthorReadPolicy(
            CorpusValues::relays($raw['fallbackRelays']),
            ...(isset($raw['maxAuthorsPerFilter']) ? ['maxAuthorsPerFilter' => new PositiveCount(CorpusValues::int($raw['maxAuthorsPerFilter']))] : []),
            ...(isset($raw['redundancy']) ? ['redundancy' => self::redundancyOf($raw['redundancy'])] : []),
        ));

        $this->assertSame($expected, array_map(static fn (AuthorReadRoute $route): array => [
            'relays' => $route->getRelays()->toStrings(),
            'authorChunks' => array_map(
                static fn ($chunk): array => array_map(static fn (PublicKey $pubkey): string => $pubkey->toHex(), $chunk->toArray()),
                $route->getAuthorChunks(),
            ),
        ], $routes->toArray()));
    }

    #[DataProvider('selectRelayHintVectors')]
    public function testSelectRelayHint(mixed $userPubkey, mixed $targetPubkey, mixed $seenOn, mixed $directory, mixed $expected): void
    {
        $target = new RelayHintTarget(CorpusValues::pubkey($targetPubkey), null === $seenOn ? null : CorpusValues::relayUrl($seenOn));
        $hint = RelayHintSelector::select(CorpusValues::pubkey($userPubkey), $target, CorpusValues::directory($directory));

        $this->assertSame($expected, null === $hint ? null : (string) $hint);
    }

    #[DataProvider('selectZapRequestRelaysVectors')]
    public function testSelectZapRequestRelays(mixed $zapperPubkey, mixed $recipientPubkey, mixed $directory, mixed $expected): void
    {
        $relays = ZapRequestRelaySelector::select(CorpusValues::pubkey($zapperPubkey), CorpusValues::pubkey($recipientPubkey), CorpusValues::directory($directory));

        $this->assertSame($expected, $relays->toStrings());
    }

    #[DataProvider('recipientsWithoutInboxVectors')]
    public function testRecipientsWithoutInbox(mixed $event, mixed $directory, mixed $expected): void
    {
        $recipients = RecipientsWithoutInboxFinder::find(CorpusValues::event($event), CorpusValues::directory($directory));

        $this->assertSame($expected, array_map(static fn (PublicKey $pubkey): string => $pubkey->toHex(), $recipients->toArray()));
    }

    #[DataProvider('findFilterPatternVectors')]
    public function testFindFilterPattern(mixed $filters, mixed $expected): void
    {
        $this->assertSame($expected, FilterPatternClassifier::classify(CorpusValues::filters($filters))->getBranch()->value);
    }

    #[DataProvider('classifyUrlVectors')]
    public function testClassifyUrl(mixed $input, mixed $isOnion, mixed $isLoopback, mixed $isLocalAddr, mixed $isInsecure): void
    {
        $url = CorpusValues::relayUrl($input);

        $this->assertSame(
            [$isOnion, $isLoopback, $isLocalAddr, $isInsecure],
            [$url->isOnion(), $url->isLoopback(), $url->isLocalAddr(), $url->isInsecure()],
        );
    }

    #[DataProvider('createEventVectors')]
    public function testCreateEvent(mixed $input, mixed $valid): void
    {
        $this->assertSame($valid, null !== Event::tryFromRaw($input));
    }

    #[DataProvider('createFilterVectors')]
    public function testCreateFilter(mixed $input, mixed $valid): void
    {
        $this->assertSame($valid, null !== Filter::tryFromRaw($input));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function normaliseUrlVectors(): iterable
    {
        return self::vectors('normalise-url.json', ['input', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function normaliseUrlIdempotencyVectors(): iterable
    {
        foreach (CorpusLoader::load('normalise-url.json') as $vector) {
            if (is_string($vector['expected'])) {
                yield CorpusValues::string($vector['name']) => [$vector['expected']];
            }
        }
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function buildRelaySetVectors(): iterable
    {
        return self::vectors('build-relay-set.json', ['input', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function extractRelayUrlsVectors(): iterable
    {
        foreach (CorpusLoader::load('extract-relay-urls.json') as $vector) {
            yield CorpusValues::string($vector['role']).' — '.CorpusValues::string($vector['name']) => [$vector['role'], $vector['tags'], $vector['expected']];
        }
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function relaysOfVectors(): iterable
    {
        return self::vectors('relays-of.json', ['pubkey', 'role', 'directory', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function routePublishVectors(): iterable
    {
        return self::vectors('route-publish.json', ['event', 'directory', 'policy', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function routeReadVectors(): iterable
    {
        return self::vectors('route-read.json', ['filters', 'directory', 'policy', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function routeAuthorReadsVectors(): iterable
    {
        return self::vectors('route-author-reads.json', ['authors', 'directory', 'policy', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function selectRelayHintVectors(): iterable
    {
        return self::vectors('select-relay-hint.json', ['userPubkey', 'targetPubkey', 'seenOn', 'directory', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function selectZapRequestRelaysVectors(): iterable
    {
        return self::vectors('select-zap-request-relays.json', ['zapperPubkey', 'recipientPubkey', 'directory', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function recipientsWithoutInboxVectors(): iterable
    {
        return self::vectors('recipients-without-inbox.json', ['event', 'directory', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function findFilterPatternVectors(): iterable
    {
        return self::vectors('find-filter-pattern.json', ['filters', 'expected']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function classifyUrlVectors(): iterable
    {
        return self::vectors('classify-url.json', ['input', 'isOnion', 'isLoopback', 'isLocalAddr', 'isInsecure']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function createEventVectors(): iterable
    {
        return self::vectors('create-event.json', ['input', 'valid']);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function createFilterVectors(): iterable
    {
        return self::vectors('create-filter.json', ['input', 'valid']);
    }

    /**
     * @param list<string> $fields
     *
     * @return iterable<string, list<mixed>>
     */
    private static function vectors(string $file, array $fields): iterable
    {
        foreach (CorpusLoader::load($file) as $vector) {
            yield CorpusValues::string($vector['name']) => array_map(static fn (string $field): mixed => $vector[$field] ?? null, $fields);
        }
    }

    private static function normalise(?string $input): ?string
    {
        $url = RelayUrl::tryFromString($input);

        return null === $url ? null : (string) $url;
    }

    private static function perRecipientCapOf(mixed $value): PerRecipientCap
    {
        return 'all' === $value ? PerRecipientCap::all() : new PerRecipientCap(CorpusValues::int($value));
    }

    private static function redundancyOf(mixed $value): Redundancy
    {
        return 'all' === $value ? Redundancy::all() : new Redundancy(CorpusValues::int($value));
    }

    /**
     * @return array<string, mixed>
     */
    private static function outcomeOf(PublishRoute|ReadRoute|NoDmRelaysFailure $result): array
    {
        return $result instanceof NoDmRelaysFailure
            ? ['failure' => $result->value]
            : ['branch' => $result->getBranch()->value, 'relays' => $result->getRelays()->toStrings()];
    }
}
