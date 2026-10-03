<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Policy;

use InvalidArgumentException;

final readonly class PositiveCount
{
    /** @var positive-int */
    private int $value;

    public function __construct(int $value)
    {
        $this->value = $value >= 1 ? $value : throw new InvalidArgumentException(sprintf('Expected a positive count, got %d', $value));
    }

    /**
     * @return positive-int
     */
    public function getValue(): int
    {
        return $this->value;
    }
}
