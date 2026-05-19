<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\PublishBranch;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\PublishContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\PublishRoute;

final class RoutePublishService
{
    public static function route(Event $event, PublishContext $context): PublishRoute
    {
        $branch = match (true) {
            EventKind::GiftWrap->value === $event->getKind() => PublishBranch::Dm,
            EventKind::isDraft($event->getKind()) => PublishBranch::Draft,
            default => PublishBranch::General,
        };
        if (PublishBranch::Dm === $branch) {
            $relays = self::dmRelays($event, $context);
            if (null === $relays) {
                return new PublishRoute($branch, null);
            }
            $afterBlock = RelaySetBuilder::subtract($relays, $context->getBlockedRelays());

            return new PublishRoute($branch, [] === $afterBlock ? null : $afterBlock);
        }

        $relays = match ($branch) {
            PublishBranch::Draft => self::draftRelays($context),
            PublishBranch::General => self::generalRelays($event, $context),
        };

        return new PublishRoute($branch, RelaySetBuilder::subtract($relays, $context->getBlockedRelays()));
    }

    private static function generalRelays(Event $event, PublishContext $context): array
    {
        $inbox = EventKind::isInboxFanout($event->getKind())
            ? self::recipientInboxFanout($event, $context->getRelayListEvents(), $context->getPerRecipientCap())
            : [];

        $indexers = EventKind::isIndexed($event->getKind()) ? $context->getIndexerRelays() : [];

        return RelaySetBuilder::build(self::userOutbox($context), $inbox, $indexers);
    }

    private static function dmRelays(Event $event, PublishContext $context): ?array
    {
        $dmRelays = [];
        foreach (self::uniqueRecipientsInOrder($event) as $recipient) {
            $dmList = EventSelector::newestByPubkeyAndKind(
                $context->getRelayListEvents(),
                $recipient['pubkey'],
                EventKind::DmRelayList->value,
            );
            if (null === $dmList) {
                continue;
            }
            foreach (RelayListExtractor::dm($dmList->getTags()) as $url) {
                $dmRelays[] = $url;
            }
        }
        $relays = RelaySetBuilder::build($dmRelays);

        return [] === $relays ? null : $relays;
    }

    private static function draftRelays(PublishContext $context): array
    {
        if ([] !== $context->getPrivateContentRelays()) {
            return RelaySetBuilder::build($context->getPrivateContentRelays());
        }

        return RelaySetBuilder::build(self::userOutbox($context));
    }

    private static function userOutbox(PublishContext $context): array
    {
        $list = EventSelector::newestByPubkeyAndKind(
            $context->getRelayListEvents(),
            $context->getUserPubkey(),
            EventKind::RelayList->value,
        );

        return null !== $list ? RelayListExtractor::outbox($list->getTags()) : [];
    }

    private static function recipientInboxFanout(Event $event, array $relayListEvents, int $cap): array
    {
        $out = [];
        foreach (self::uniqueRecipientsInOrder($event) as $recipient) {
            foreach (self::recipientInboxRelays($recipient, $relayListEvents, $cap) as $url) {
                $out[] = $url;
            }
        }

        return $out;
    }

    private static function recipientInboxRelays(array $recipient, array $relayListEvents, int $cap): array
    {
        $relayList = EventSelector::newestByPubkeyAndKind(
            $relayListEvents,
            $recipient['pubkey'],
            EventKind::RelayList->value,
        );
        if (null !== $relayList) {
            $inbox = RelayListExtractor::inbox($relayList->getTags());
            if ([] !== $inbox) {
                return array_slice($inbox, 0, $cap);
            }
        }

        $hint = $recipient['hint'];

        return null !== $hint ? [$hint] : [];
    }

    private static function uniqueRecipientsInOrder(Event $event): array
    {
        $result = [];
        $seen = [];
        foreach ($event->getTags() as $tag) {
            if ('p' !== $tag->getValue(0)) {
                continue;
            }
            $hex = $tag->getValue(1);
            if (null === $hex || isset($seen[$hex])) {
                continue;
            }
            $pubkey = PublicKey::fromHex($hex);
            if (null === $pubkey) {
                continue;
            }
            $seen[$hex] = true;
            $rawHint = $tag->getValue(2);
            $hint = null !== $rawHint && '' !== $rawHint ? RelayUrl::fromString($rawHint) : null;
            $result[] = ['pubkey' => $pubkey, 'hint' => $hint];
        }

        return $result;
    }
}
