<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\PublishBranch;
use Innis\Nostr\RelaySelection\Domain\Failure\NoDmRelaysFailure;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PerRecipientCap;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PublishPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\PublishRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\GroupClaim;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\Recipient;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;

final class PublishRouter
{
    public static function route(Event $event, RelayDirectory $directory, PublishPolicy $policy = new PublishPolicy()): PublishRoute|NoDmRelaysFailure
    {
        $kind = EventKind::tryFrom($event->getKind());
        if ($kind?->isGiftWrap() ?? false) {
            return self::dmRoute($event, $directory);
        }
        if ($kind?->isDraft() ?? false) {
            return new PublishRoute(PublishBranch::Draft, self::draftRelays($event, $directory, $policy));
        }
        $group = GroupClaimExtractor::fromEvent($event);
        // Deliberate: a group event keeps the group route with no relays rather than take a public one — see ADR-0011
        if (null !== $group) {
            return new PublishRoute(PublishBranch::Group, self::groupRelays($group, $directory, $policy));
        }

        return new PublishRoute(PublishBranch::General, self::generalRelays($event, $directory, $policy));
    }

    private static function dmRoute(Event $event, RelayDirectory $directory): PublishRoute|NoDmRelaysFailure
    {
        $relays = new RelaySet()->union(...array_map(
            static fn (Recipient $recipient): RelaySet => $directory->relaysOf($recipient->getPubkey(), RelayRole::Dm),
            RecipientExtractor::fromEvent($event)->toArray(),
        ));

        return $relays->isEmpty() ? NoDmRelaysFailure::NoDmRelays : new PublishRoute(PublishBranch::Dm, $relays);
    }

    private static function draftRelays(Event $event, RelayDirectory $directory, PublishPolicy $policy): RelaySet
    {
        $privateRelays = $directory->permitted($policy->getPrivateContentRelays());

        return $privateRelays->isEmpty() ? $directory->relaysOf($event->getPubkey(), RelayRole::Outbox) : $privateRelays;
    }

    private static function groupRelays(GroupClaim $group, RelayDirectory $directory, PublishPolicy $policy): RelaySet
    {
        return $group->namesGroup() ? $directory->permitted(self::relaySetOf($group->getHint()), $policy->getGroupRelays()) : new RelaySet();
    }

    private static function generalRelays(Event $event, RelayDirectory $directory, PublishPolicy $policy): RelaySet
    {
        $kind = EventKind::tryFrom($event->getKind());
        // Deliberate: NIP-A4 sends a kind 24 to every inbox relay of each receiver, so no cap applies — see ADR-0018
        $cap = EventKind::PublicMessage === $kind ? PerRecipientCap::all() : $policy->getPerRecipientCap();
        $fanout = array_map(
            static fn (Recipient $recipient): RelaySet => self::inboxOrHint($recipient, $directory, $cap),
            RecipientExtractor::fromEvent($event)->toArray(),
        );
        $indexers = ($kind?->isIndexed() ?? false) ? $directory->permitted($policy->getIndexerRelays()) : new RelaySet();

        return $directory->relaysOf($event->getPubkey(), RelayRole::Outbox)->union(...$fanout)->union($indexers);
    }

    private static function inboxOrHint(Recipient $recipient, RelayDirectory $directory, PerRecipientCap $cap): RelaySet
    {
        $inbox = $directory->relaysOf($recipient->getPubkey(), RelayRole::Inbox);
        $candidates = $inbox->isEmpty() ? $directory->permitted(self::relaySetOf($recipient->getHint())) : $inbox;

        return $cap->limit($candidates);
    }

    private static function relaySetOf(?RelayUrl $hint): RelaySet
    {
        return new RelaySet(null === $hint ? [] : [$hint]);
    }
}
