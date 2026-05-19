<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Entity\Event;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final class EventSelector
{
    public static function newestByPubkeyAndKind(array $events, PublicKey $pubkey, int $kind): ?Event
    {
        $newest = null;
        foreach ($events as $event) {
            if ($event->getKind() !== $kind) {
                continue;
            }
            if (!$event->getPubkey()->equals($pubkey)) {
                continue;
            }
            if (null === $newest || $event->getCreatedAt() > $newest->getCreatedAt()) {
                $newest = $event;
            }
        }

        return $newest;
    }
}
