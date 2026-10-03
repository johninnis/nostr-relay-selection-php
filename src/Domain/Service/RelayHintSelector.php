<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayHintTarget;

final class RelayHintSelector
{
    public static function select(PublicKey $userPubkey, RelayHintTarget $target, RelayDirectory $directory): ?RelayUrl
    {
        $seenOn = $target->getSeenOn();

        return $directory->permitted(
            new RelaySet(null === $seenOn ? [] : [$seenOn]),
            $directory->relaysOf($target->getPubkey(), RelayRole::Outbox),
            $directory->relaysOf($userPubkey, RelayRole::Inbox),
        )->first();
    }
}
