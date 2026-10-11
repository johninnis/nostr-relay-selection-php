<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Routing;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;

final readonly class GroupClaim
{
    private function __construct(
        private bool $namesGroup,
        private ?RelayUrl $hint,
    ) {
    }

    public static function namingGroup(?RelayUrl $hint): self
    {
        return new self(true, $hint);
    }

    public static function namingNoGroup(): self
    {
        return new self(false, null);
    }

    public function namesGroup(): bool
    {
        return $this->namesGroup;
    }

    public function getHint(): ?RelayUrl
    {
        return $this->hint;
    }
}
