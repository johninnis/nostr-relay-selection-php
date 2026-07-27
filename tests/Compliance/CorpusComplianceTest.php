<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Compliance;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Entity\Filter;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\AuthorRelaySelector;
use Innis\Nostr\RelaySelection\Domain\Service\FilterPatternClassifier;
use Innis\Nostr\RelaySelection\Domain\Service\MissingRelayListFinder;
use Innis\Nostr\RelaySelection\Domain\Service\PublishRouter;
use Innis\Nostr\RelaySelection\Domain\Service\ReadRouter;
use Innis\Nostr\RelaySelection\Domain\Service\RelayHintSelector;
use Innis\Nostr\RelaySelection\Domain\Service\RelayListExtractor;
use Innis\Nostr\RelaySelection\Domain\Service\RelaySetBuilder;
use Innis\Nostr\RelaySelection\Domain\Service\ZapRequestRelaySelector;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorReadRouteContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorRelaysContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\PublishContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\ReadContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\RelayHintContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\ZapRequestContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;
use Innis\Nostr\RelaySelection\Tests\Support\CorpusLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CorpusComplianceTest extends TestCase
{
    #[DataProvider('normaliseUrlVectors')]
    public function testNormaliseUrl(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, self::normaliseFromInput($input));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function normaliseUrlVectors(): iterable
    {
        foreach (CorpusLoader::load('normalise-url.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['input'], $vector['expected']];
        }
    }

    #[DataProvider('normaliseUrlIdempotencyVectors')]
    public function testNormaliseUrlIsIdempotent(string $normalised): void
    {
        $this->assertSame($normalised, self::normaliseFromInput($normalised));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function normaliseUrlIdempotencyVectors(): iterable
    {
        foreach (CorpusLoader::load('normalise-url.json') as $vector) {
            if (null === $vector['expected']) {
                continue;
            }
            yield self::expectString($vector['name']) => [$vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawInput
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('buildRelaySetVectors')]
    public function testBuildRelaySet(array $rawInput, array $expected): void
    {
        $sources = array_map(
            static fn (mixed $source): array => array_map(
                static fn (mixed $url): ?RelayUrl => is_string($url) ? RelayUrl::tryFromString($url) : null,
                self::expectList($source),
            ),
            $rawInput,
        );

        $this->assertSame($expected, self::relayUrlsToStrings(RelaySetBuilder::build(...$sources)));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function buildRelaySetVectors(): iterable
    {
        foreach (CorpusLoader::load('build-relay-set.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['input'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawTags
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('extractRelayUrlsVectors')]
    public function testExtractRelayUrls(string $function, array $rawTags, array $expected): void
    {
        $tags = self::expectTags($rawTags);
        $actual = match ($function) {
            'extractInboxRelayUrls' => RelayListExtractor::inbox($tags),
            'extractOutboxRelayUrls' => RelayListExtractor::outbox($tags),
            'extractDmRelayUrls' => RelayListExtractor::dm($tags),
            'extractBlockedRelayUrls' => RelayListExtractor::blocked($tags),
            'extractSearchRelayUrls' => RelayListExtractor::search($tags),
            default => throw new RuntimeException('Unknown extractor: '.$function),
        };

        $this->assertSame($expected, self::relayUrlsToStrings($actual));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function extractRelayUrlsVectors(): iterable
    {
        foreach (CorpusLoader::load('extract-relay-urls.json') as $vector) {
            $label = self::expectString($vector['function']).' — '.self::expectString($vector['name']);
            yield $label => [
                $vector['function'],
                $vector['tags'],
                $vector['expected'],
            ];
        }
    }

    /**
     * @param array<string, mixed>    $rawEvent
     * @param array<string, mixed>    $rawContext
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('routePublishVectors')]
    public function testRoutePublish(array $rawEvent, array $rawContext, array $expected): void
    {
        $event = self::expectEvent($rawEvent);
        $context = new PublishContext(
            self::expectPubkey($rawContext['userPubkey']),
            self::expectEvents($rawContext['relayListEvents']),
            self::expectRelayUrls($rawContext['privateContentRelays']),
            self::expectRelayUrls($rawContext['indexerRelays']),
            self::rawIntOr($rawContext, 'perRecipientCap', PublishContext::DEFAULT_PER_RECIPIENT_CAP),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'blockedRelays', [])),
        );
        $route = PublishRouter::route($event, $context);

        $this->assertSame($expected['branch'], $route->getBranch()->value);
        $this->assertSame($expected['relays'], self::relayUrlsToStrings($route->getRelays()));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function routePublishVectors(): iterable
    {
        foreach (CorpusLoader::load('route-publish.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['event'], $vector['context'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawContext
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('routeReadVectors')]
    public function testRouteRead(array $rawContext, array $expected): void
    {
        $context = new ReadContext(
            self::expectRelayUrls($rawContext['userRelayUrls']),
            self::expectRelayUrls($rawContext['callerRelays']),
            self::expectFilters($rawContext['filters']),
            self::expectEvents($rawContext['relayListEvents']),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'blockedRelays', [])),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'searchRelays', [])),
        );
        $route = ReadRouter::route($context);

        $this->assertSame($expected['branch'], $route->getBranch()->value);
        $this->assertSame($expected['relays'], self::relayUrlsToStrings($route->getRelays()));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function routeReadVectors(): iterable
    {
        foreach (CorpusLoader::load('route-read.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['context'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawContext
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('routeAuthorReadsVectors')]
    public function testRouteAuthorReads(array $rawContext, array $expected): void
    {
        $context = new AuthorReadRouteContext(
            self::expectPubkeys($rawContext['authorPubkeys']),
            self::expectEvents($rawContext['relayListEvents']),
            self::expectRelayUrls($rawContext['fallbackRelays']),
            self::rawIntOr($rawContext, 'maxAuthorsPerFilter', AuthorReadRouteContext::DEFAULT_MAX_AUTHORS_PER_FILTER),
            self::rawNullableIntOr($rawContext, 'redundancy', AuthorReadRouteContext::DEFAULT_REDUNDANCY),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'blockedRelays', [])),
        );
        $routes = AuthorReadRouter::route($context);
        $actual = array_map(static fn ($route) => [
            'relays' => self::relayUrlsToStrings($route->getRelays()),
            'authorChunks' => array_map(self::pubkeysToHex(...), $route->getAuthorChunks()),
        ], $routes);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function routeAuthorReadsVectors(): iterable
    {
        foreach (CorpusLoader::load('route-author-reads.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['context'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawContext
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('selectAuthorInboxRelaysVectors')]
    public function testSelectAuthorInboxRelays(array $rawContext, array $expected): void
    {
        $context = new AuthorRelaysContext(
            self::expectPubkey($rawContext['authorPubkey']),
            self::expectEvents($rawContext['relayListEvents']),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'blockedRelays', [])),
        );

        $this->assertSame($expected, self::relayUrlsToStrings(AuthorRelaySelector::inbox($context)));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function selectAuthorInboxRelaysVectors(): iterable
    {
        foreach (CorpusLoader::load('select-author-inbox-relays.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['context'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawContext
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('selectZapRequestRelaysVectors')]
    public function testSelectZapRequestRelays(array $rawContext, array $expected): void
    {
        $context = new ZapRequestContext(
            self::expectPubkey($rawContext['zapperPubkey']),
            self::expectPubkey($rawContext['recipientPubkey']),
            self::expectEvents($rawContext['relayListEvents']),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'blockedRelays', [])),
        );

        $this->assertSame($expected, self::relayUrlsToStrings(ZapRequestRelaySelector::select($context)));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function selectZapRequestRelaysVectors(): iterable
    {
        foreach (CorpusLoader::load('select-zap-request-relays.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['context'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed> $rawContext
     */
    #[DataProvider('selectRelayHintVectors')]
    public function testSelectRelayHint(array $rawContext, ?string $expected): void
    {
        $context = new RelayHintContext(
            self::expectPubkey($rawContext['targetPubkey']),
            self::expectPubkey($rawContext['userPubkey']),
            self::expectEvents($rawContext['relayListEvents']),
            self::expectRelayUrls(self::rawArrayOr($rawContext, 'blockedRelays', [])),
        );
        $hint = RelayHintSelector::select($context);

        $this->assertSame($expected, null !== $hint ? (string) $hint : null);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function selectRelayHintVectors(): iterable
    {
        foreach (CorpusLoader::load('select-relay-hint.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['context'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed>    $rawEvent
     * @param array<string, mixed>    $rawRelayListEvents
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('missingRelayListPubkeysVectors')]
    public function testMissingRelayListPubkeys(array $rawEvent, array $rawRelayListEvents, array $expected): void
    {
        $event = self::expectEvent($rawEvent);
        $relayListEvents = self::expectEvents($rawRelayListEvents);

        $this->assertSame($expected, self::pubkeysToHex(MissingRelayListFinder::find($event, $relayListEvents)));
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function missingRelayListPubkeysVectors(): iterable
    {
        foreach (CorpusLoader::load('missing-relay-list-pubkeys.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['event'], $vector['relayListEvents'], $vector['expected']];
        }
    }

    /**
     * @param array<string, mixed> $rawFilters
     */
    #[DataProvider('findFilterPatternVectors')]
    public function testFindFilterPattern(array $rawFilters, string $expected): void
    {
        $filters = self::expectFilters($rawFilters);
        $this->assertSame($expected, FilterPatternClassifier::classify($filters)->value);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function findFilterPatternVectors(): iterable
    {
        foreach (CorpusLoader::load('find-filter-pattern.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['filters'], $vector['expected']];
        }
    }

    #[DataProvider('createEventVectors')]
    public function testCreateEvent(mixed $input, bool $valid): void
    {
        $event = Event::tryFromRaw($input);
        $this->assertSame($valid, null !== $event);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function createEventVectors(): iterable
    {
        foreach (CorpusLoader::load('create-event.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['input'], $vector['valid']];
        }
    }

    #[DataProvider('createFilterVectors')]
    public function testCreateFilter(mixed $input, bool $valid): void
    {
        $filter = Filter::tryFromRaw($input);
        $this->assertSame($valid, null !== $filter);
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function createFilterVectors(): iterable
    {
        foreach (CorpusLoader::load('create-filter.json') as $vector) {
            yield self::expectString($vector['name']) => [$vector['input'], $vector['valid']];
        }
    }

    #[DataProvider('classifyUrlVectors')]
    public function testClassifyUrl(string $input, bool $isOnion, bool $isLoopback, bool $isLocalAddr, bool $isInsecure): void
    {
        $url = RelayUrl::tryFromString($input);
        $this->assertNotNull($url, "expected $input to parse");
        $this->assertSame($isOnion, $url->isOnion(), 'isOnion');
        $this->assertSame($isLoopback, $url->isLoopback(), 'isLoopback');
        $this->assertSame($isLocalAddr, $url->isLocalAddr(), 'isLocalAddr');
        $this->assertSame($isInsecure, $url->isInsecure(), 'isInsecure');
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function classifyUrlVectors(): iterable
    {
        foreach (CorpusLoader::load('classify-url.json') as $vector) {
            yield self::expectString($vector['name']) => [
                $vector['input'],
                $vector['isOnion'],
                $vector['isLoopback'],
                $vector['isLocalAddr'],
                $vector['isInsecure'],
            ];
        }
    }

    private static function normaliseFromInput(?string $input): ?string
    {
        if (null === $input) {
            return null;
        }
        $relay = RelayUrl::tryFromString($input);

        return null === $relay ? null : (string) $relay;
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

    private static function expectEvent(mixed $raw): Event
    {
        $event = Event::tryFromRaw($raw);
        if (null === $event) {
            throw new RuntimeException('Invalid event in fixture: '.json_encode($raw));
        }

        return $event;
    }

    /**
     * @return list<Event>
     */
    private static function expectEvents(mixed $raw): array
    {
        return array_map(self::expectEvent(...), self::expectList($raw));
    }

    private static function expectPubkey(mixed $hex): PublicKey
    {
        $pubkey = is_string($hex) ? PublicKey::tryFromHex($hex) : null;
        if (null === $pubkey) {
            throw new RuntimeException('Invalid pubkey in fixture: '.get_debug_type($hex));
        }

        return $pubkey;
    }

    /**
     * @return list<PublicKey>
     */
    private static function expectPubkeys(mixed $hexes): array
    {
        return array_map(self::expectPubkey(...), self::expectList($hexes));
    }

    /**
     * @return list<RelayUrl>
     */
    private static function expectRelayUrls(mixed $urls): array
    {
        return array_map(static function (mixed $url): RelayUrl {
            if (!is_string($url)) {
                throw new RuntimeException('Relay URL in fixture must be a string');
            }
            $relay = RelayUrl::tryFromString($url);
            if (null === $relay) {
                throw new RuntimeException(sprintf('Invalid relay URL in fixture: %s', $url));
            }

            return $relay;
        }, self::expectList($urls));
    }

    private static function expectTag(mixed $raw): Tag
    {
        $tag = Tag::tryFromRaw($raw);
        if (null === $tag) {
            throw new RuntimeException('Invalid tag in fixture: '.json_encode($raw));
        }

        return $tag;
    }

    /**
     * @return list<Tag>
     */
    private static function expectTags(mixed $raw): array
    {
        return array_map(self::expectTag(...), self::expectList($raw));
    }

    private static function expectFilter(mixed $raw): Filter
    {
        $filter = Filter::tryFromRaw($raw);
        if (null === $filter) {
            throw new RuntimeException('Invalid filter in fixture: '.json_encode($raw));
        }

        return $filter;
    }

    /**
     * @return list<Filter>
     */
    private static function expectFilters(mixed $raw): array
    {
        return array_map(self::expectFilter(...), self::expectList($raw));
    }

    /**
     * @param ?array<string, mixed> $relays
     */
    /**
     * @param ?list<RelayUrl> $relays
     *
     * @return ?list<string>
     */
    private static function relayUrlsToStrings(?array $relays): ?array
    {
        if (null === $relays) {
            return null;
        }

        return array_map(static fn (RelayUrl $r) => (string) $r, $relays);
    }

    /**
     * @param array<string, mixed> $pubkeys
     */
    /**
     * @param list<PublicKey> $pubkeys
     *
     * @return list<string>
     */
    private static function pubkeysToHex(array $pubkeys): array
    {
        return array_map(static fn (PublicKey $p) => $p->toHex(), $pubkeys);
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $default
     */
    /**
     * @param array<string, mixed>    $raw
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    private static function rawArrayOr(array $raw, string $key, array $default): array
    {
        if (!array_key_exists($key, $raw)) {
            return $default;
        }
        $value = $raw[$key];

        return is_array($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $raw
     */
    /**
     * @param array<string, mixed> $raw
     */
    private static function rawIntOr(array $raw, string $key, int $default): int
    {
        if (!array_key_exists($key, $raw)) {
            return $default;
        }
        $value = $raw[$key];

        return is_int($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $raw
     */
    /**
     * @param array<string, mixed> $raw
     */
    private static function rawNullableIntOr(array $raw, string $key, ?int $default): ?int
    {
        if (!array_key_exists($key, $raw)) {
            return $default;
        }
        $value = $raw[$key];
        if (null === $value) {
            return null;
        }

        return is_int($value) ? $value : $default;
    }
}
