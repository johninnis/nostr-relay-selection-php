<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Support;

use Innis\Nostr\RelaySelection\Domain\Collection\EventCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\FilterCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Collection\TagCollection;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;
use RuntimeException;

final class CorpusValues
{
    /**
     * @return list<mixed>
     */
    public static function list(mixed $raw): array
    {
        return is_array($raw) && array_is_list($raw) ? $raw : throw new RuntimeException('Expected a list in fixture, got '.get_debug_type($raw));
    }

    /**
     * @return array<string, mixed>
     */
    public static function object(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new RuntimeException('Expected an object in fixture, got '.get_debug_type($raw));
        }
        $named = [];
        foreach ($raw as $key => $value) {
            $named[(string) $key] = $value;
        }

        return $named;
    }

    public static function string(mixed $raw): string
    {
        return is_string($raw) ? $raw : throw new RuntimeException('Expected a string in fixture, got '.get_debug_type($raw));
    }

    public static function int(mixed $raw): int
    {
        return is_int($raw) ? $raw : throw new RuntimeException('Expected an integer in fixture, got '.get_debug_type($raw));
    }

    public static function pubkey(mixed $raw): PublicKey
    {
        return PublicKey::tryFromHex(self::string($raw)) ?? throw new RuntimeException('Invalid pubkey in fixture: '.self::string($raw));
    }

    public static function pubkeys(mixed $raw): PublicKeyCollection
    {
        return new PublicKeyCollection(array_map(self::pubkey(...), self::list($raw)));
    }

    public static function relayUrl(mixed $raw): RelayUrl
    {
        return RelayUrl::tryFromString(self::string($raw)) ?? throw new RuntimeException('Invalid relay URL in fixture: '.self::string($raw));
    }

    public static function relays(mixed $raw): RelaySet
    {
        return new RelaySet(array_map(self::relayUrl(...), self::list($raw)));
    }

    public static function event(mixed $raw): Event
    {
        return Event::tryFromRaw($raw) ?? throw new RuntimeException('Invalid event in fixture: '.json_encode($raw));
    }

    public static function events(mixed $raw): EventCollection
    {
        return new EventCollection(array_map(self::event(...), self::list($raw)));
    }

    public static function tags(mixed $raw): TagCollection
    {
        return new TagCollection(array_map(
            static fn (mixed $tag): Tag => Tag::tryFromRaw($tag) ?? throw new RuntimeException('Invalid tag in fixture: '.json_encode($tag)),
            self::list($raw),
        ));
    }

    public static function filters(mixed $raw): FilterCollection
    {
        return new FilterCollection(array_map(
            static fn (mixed $filter): Filter => Filter::tryFromRaw($filter) ?? throw new RuntimeException('Invalid filter in fixture: '.json_encode($filter)),
            self::list($raw),
        ));
    }

    public static function role(mixed $raw): RelayRole
    {
        return RelayRole::from(self::string($raw));
    }

    public static function directory(mixed $raw): RelayDirectory
    {
        $directory = self::object($raw);

        return RelayDirectory::fromEvents(self::events($directory['relayListEvents']), self::relays($directory['blockedRelays']));
    }

    public static function isInvalidArgument(mixed $expected): bool
    {
        return is_array($expected) && 'invalidArgument' === ($expected['error'] ?? null);
    }
}
