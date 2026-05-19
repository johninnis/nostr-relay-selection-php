<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final class MissingRelayListPubkeysService
{
    public static function find(Event $event, array $relayListEvents): array
    {
        if (!EventKind::isInboxFanout($event->getKind())) {
            return [];
        }

        $ptagged = self::collectPTaggedPubkeys($event);
        $withRelayList = self::collectPubkeysWithRelayList($ptagged, $relayListEvents);

        $missing = [];
        foreach ($ptagged as $hex => $pubkey) {
            if (!isset($withRelayList[$hex])) {
                $missing[] = $pubkey;
            }
        }

        return $missing;
    }

    private static function collectPTaggedPubkeys(Event $event): array
    {
        $result = [];
        foreach ($event->getTags() as $tag) {
            if ('p' !== $tag->getValue(0)) {
                continue;
            }
            $hex = $tag->getValue(1);
            if (null === $hex || isset($result[$hex])) {
                continue;
            }
            $pubkey = PublicKey::fromHex($hex);
            if (null === $pubkey) {
                continue;
            }
            $result[$hex] = $pubkey;
        }

        return $result;
    }

    private static function collectPubkeysWithRelayList(array $ptagged, array $relayListEvents): array
    {
        $set = [];
        foreach ($relayListEvents as $event) {
            if (EventKind::RelayList->value !== $event->getKind()) {
                continue;
            }
            $hex = $event->getPubkey()->toHex();
            if (isset($ptagged[$hex])) {
                $set[$hex] = true;
            }
        }

        return $set;
    }
}
