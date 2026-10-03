<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\PublishBranch;
use Innis\Nostr\RelaySelection\Domain\Failure\NoDmRelaysFailure;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PublishPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\PublishRoute;
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
        $groupRelays = self::groupRelays($event, $directory, $policy);
        if (!$groupRelays->isEmpty()) {
            return new PublishRoute(PublishBranch::Group, $groupRelays);
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

    private static function groupRelays(Event $event, RelayDirectory $directory, PublishPolicy $policy): RelaySet
    {
        $groupTag = array_find(
            $event->getTags()->toArray(),
            static fn (Tag $tag): bool => 'h' === $tag->getValue(0) && '' !== ($tag->getValue(1) ?? ''),
        );
        if (null === $groupTag) {
            return new RelaySet();
        }

        return $directory->permitted(RelaySet::fromStrings([$groupTag->getValue(2)]), $policy->getGroupRelays());
    }

    private static function generalRelays(Event $event, RelayDirectory $directory, PublishPolicy $policy): RelaySet
    {
        $kind = EventKind::tryFrom($event->getKind());
        $fanout = array_map(
            static fn (Recipient $recipient): RelaySet => self::inboxOrHint($recipient, $directory, $policy),
            RecipientExtractor::fromEvent($event)->toArray(),
        );
        $indexers = ($kind?->isIndexed() ?? false) ? $directory->permitted($policy->getIndexerRelays()) : new RelaySet();

        return $directory->relaysOf($event->getPubkey(), RelayRole::Outbox)->union(...$fanout)->union($indexers);
    }

    private static function inboxOrHint(Recipient $recipient, RelayDirectory $directory, PublishPolicy $policy): RelaySet
    {
        $inbox = $directory->relaysOf($recipient->getPubkey(), RelayRole::Inbox);
        $candidates = $inbox->isEmpty() ? $directory->permitted(self::hintOf($recipient)) : $inbox;

        return $policy->getPerRecipientCap()->limit($candidates);
    }

    private static function hintOf(Recipient $recipient): RelaySet
    {
        $hint = $recipient->getHint();

        return new RelaySet(null === $hint ? [] : [$hint]);
    }
}
