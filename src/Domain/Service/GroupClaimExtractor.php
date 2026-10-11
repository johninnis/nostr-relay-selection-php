<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\GroupClaim;

final class GroupClaimExtractor
{
    // Deliberate: every h tag is read as one claim, so tag order never decides the group or its hint — see ADR-0011
    public static function fromEvent(Event $event): ?GroupClaim
    {
        $groupTags = array_filter(
            $event->getTags()->toArray(),
            static fn (Tag $tag): bool => 'h' === $tag->getValue(0) && '' !== ($tag->getValue(1) ?? ''),
        );
        if ([] === $groupTags) {
            return null;
        }
        if (1 !== count(array_unique(array_map(static fn (Tag $tag): ?string => $tag->getValue(1), $groupTags)))) {
            return GroupClaim::namingNoGroup();
        }
        $hints = RelaySet::fromStrings(array_map(static fn (Tag $tag): ?string => $tag->getValue(2), $groupTags));

        return GroupClaim::namingGroup(1 === count($hints) ? $hints->first() : null);
    }
}
