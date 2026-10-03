<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Enum;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Collection\TagCollection;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;

enum RelayRole: string
{
    case Inbox = 'inbox';
    case Outbox = 'outbox';
    case Dm = 'dm';
    case Search = 'search';
    case Blocked = 'blocked';

    public function kind(): EventKind
    {
        return match ($this) {
            self::Inbox, self::Outbox => EventKind::RelayList,
            self::Dm => EventKind::DmRelayList,
            self::Search => EventKind::SearchRelaysList,
            self::Blocked => EventKind::BlockedRelaysList,
        };
    }

    public function extract(TagCollection $tags): RelaySet
    {
        return RelaySet::fromStrings(array_map(
            static fn (Tag $tag): ?string => $tag->getValue(1),
            array_filter($tags->toArray(), $this->accepts(...)),
        ));
    }

    private function accepts(Tag $tag): bool
    {
        return match ($this) {
            self::Inbox => 'r' === $tag->getValue(0) && 'write' !== $tag->getValue(2),
            self::Outbox => 'r' === $tag->getValue(0) && 'read' !== $tag->getValue(2),
            self::Dm, self::Search, self::Blocked => 'relay' === $tag->getValue(0),
        };
    }
}
