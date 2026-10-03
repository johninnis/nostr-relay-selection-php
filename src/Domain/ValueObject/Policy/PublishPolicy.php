<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Policy;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;

final readonly class PublishPolicy
{
    public function __construct(
        private RelaySet $privateContentRelays = new RelaySet(),
        private RelaySet $indexerRelays = new RelaySet(),
        private RelaySet $groupRelays = new RelaySet(),
        private PerRecipientCap $perRecipientCap = new PerRecipientCap(PHP_INT_MAX),
    ) {
    }

    public function getPrivateContentRelays(): RelaySet
    {
        return $this->privateContentRelays;
    }

    public function getIndexerRelays(): RelaySet
    {
        return $this->indexerRelays;
    }

    public function getGroupRelays(): RelaySet
    {
        return $this->groupRelays;
    }

    public function getPerRecipientCap(): PerRecipientCap
    {
        return $this->perRecipientCap;
    }
}
