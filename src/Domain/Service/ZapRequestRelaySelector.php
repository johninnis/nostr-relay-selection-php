<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\ZapRequestContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final class ZapRequestRelaySelector
{
    /**
     * @return list<RelayUrl>
     */
    public static function select(ZapRequestContext $context): array
    {
        return RelaySetBuilder::subtract(
            RelaySetBuilder::build(
                self::inboxOf($context->getRelayListEvents(), $context->getZapperPubkey()),
                self::inboxOf($context->getRelayListEvents(), $context->getRecipientPubkey()),
            ),
            $context->getBlockedRelays(),
        );
    }

    /**
     * @param list<Event> $relayListEvents
     *
     * @return list<RelayUrl>
     */
    private static function inboxOf(array $relayListEvents, PublicKey $pubkey): array
    {
        $list = EventSelector::newestByPubkeyAndKind($relayListEvents, $pubkey, EventKind::RelayList->value);
        if (null === $list) {
            return [];
        }

        return RelayListExtractor::inbox($list->getTags());
    }
}
