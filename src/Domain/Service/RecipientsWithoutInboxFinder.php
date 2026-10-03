<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\Recipient;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;

final class RecipientsWithoutInboxFinder
{
    public static function find(Event $event, RelayDirectory $directory): PublicKeyCollection
    {
        $kind = EventKind::tryFrom($event->getKind());
        if (($kind?->isGiftWrap() ?? false) || ($kind?->isDraft() ?? false)) {
            return new PublicKeyCollection();
        }
        $withoutInbox = array_filter(
            RecipientExtractor::fromEvent($event)->toArray(),
            static fn (Recipient $recipient): bool => $directory->relaysOf($recipient->getPubkey(), RelayRole::Inbox)->isEmpty(),
        );

        return new PublicKeyCollection(array_map(static fn (Recipient $recipient) => $recipient->getPubkey(), $withoutInbox));
    }
}
