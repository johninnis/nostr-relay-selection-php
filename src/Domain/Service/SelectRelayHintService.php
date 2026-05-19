<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\RelayHintContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final class SelectRelayHintService
{
    public static function select(RelayHintContext $context): ?RelayUrl
    {
        $targetList = EventSelector::newestByPubkeyAndKind(
            $context->getRelayListEvents(),
            $context->getTargetPubkey(),
            EventKind::RelayList->value,
        );
        $userList = EventSelector::newestByPubkeyAndKind(
            $context->getRelayListEvents(),
            $context->getUserPubkey(),
            EventKind::RelayList->value,
        );

        $blocked = $context->getBlockedRelays();
        $targetInbox = RelaySetBuilder::subtract(
            null !== $targetList ? RelayListExtractor::inbox($targetList->getTags()) : [],
            $blocked,
        );
        $userOutbox = RelaySetBuilder::subtract(
            null !== $userList ? RelayListExtractor::outbox($userList->getTags()) : [],
            $blocked,
        );

        $targetInboxKeys = [];
        foreach ($targetInbox as $url) {
            $targetInboxKeys[(string) $url] = $url;
        }

        foreach ($userOutbox as $url) {
            if (isset($targetInboxKeys[(string) $url])) {
                return $url;
            }
        }

        if ([] !== $targetInbox) {
            return $targetInbox[0];
        }

        if ([] !== $userOutbox) {
            return $userOutbox[0];
        }

        return null;
    }
}
