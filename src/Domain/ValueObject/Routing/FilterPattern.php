<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Routing;

use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final readonly class FilterPattern
{
    private function __construct(
        private ReadBranch $branch,
        private ?PublicKey $dmRecipient,
    ) {
    }

    public static function search(): self
    {
        return new self(ReadBranch::Search, null);
    }

    public static function dmInbox(PublicKey $recipient): self
    {
        return new self(ReadBranch::DmInbox, $recipient);
    }

    public static function general(): self
    {
        return new self(ReadBranch::General, null);
    }

    public function getBranch(): ReadBranch
    {
        return $this->branch;
    }

    public function getDmRecipient(): ?PublicKey
    {
        return $this->dmRecipient;
    }
}
