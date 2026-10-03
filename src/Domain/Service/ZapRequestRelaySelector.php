<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;

final class ZapRequestRelaySelector
{
    public static function select(PublicKey $zapperPubkey, PublicKey $recipientPubkey, RelayDirectory $directory): RelaySet
    {
        return $directory->relaysOf($zapperPubkey, RelayRole::Inbox)
            ->union($directory->relaysOf($recipientPubkey, RelayRole::Inbox));
    }
}
