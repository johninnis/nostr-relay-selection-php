<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\RecipientCollection;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\Recipient;

final class RecipientExtractor
{
    public static function fromEvent(Event $event): RecipientCollection
    {
        if (EventKind::tryFrom($event->getKind())?->isPubkeyData() ?? false) {
            return new RecipientCollection();
        }
        $byPubkey = [];
        foreach ($event->getTags() as $tag) {
            $recipient = self::recipientOf($tag);
            if (null !== $recipient) {
                $byPubkey[$recipient->getPubkey()->toHex()] ??= $recipient;
            }
        }

        return new RecipientCollection(array_values($byPubkey));
    }

    private static function recipientOf(Tag $tag): ?Recipient
    {
        $pubkey = 'p' === $tag->getValue(0) ? PublicKey::tryFromHex($tag->getValue(1) ?? '') : null;

        return null === $pubkey ? null : new Recipient($pubkey, RelayUrl::tryFromString($tag->getValue(2)));
    }
}
