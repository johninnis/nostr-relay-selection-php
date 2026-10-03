<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Policy;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;

final readonly class PerRecipientCap
{
    private PositiveCount $relaysPerRecipient;

    public function __construct(int $relaysPerRecipient)
    {
        $this->relaysPerRecipient = new PositiveCount($relaysPerRecipient);
    }

    public static function all(): self
    {
        return new self(PHP_INT_MAX);
    }

    public function limit(RelaySet $relays): RelaySet
    {
        return $relays->take($this->relaysPerRecipient->getValue());
    }
}
