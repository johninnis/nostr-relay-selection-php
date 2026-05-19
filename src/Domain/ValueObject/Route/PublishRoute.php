<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Route;

use Innis\Nostr\RelaySelection\Domain\Enum\Route\PublishBranch;

final readonly class PublishRoute
{
    public function __construct(
        private PublishBranch $branch,
        private ?array $relays,
    ) {
    }

    public function getBranch(): PublishBranch
    {
        return $this->branch;
    }

    public function getRelays(): ?array
    {
        return $this->relays;
    }
}
