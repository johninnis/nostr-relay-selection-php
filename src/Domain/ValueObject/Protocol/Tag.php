<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0003

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol;

use InvalidArgumentException;

final readonly class Tag
{
    /** @var list<string> */
    private array $values;

    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(array $values)
    {
        $this->values = array_values(array_map(
            static fn (mixed $value): string => is_string($value)
                ? $value
                : throw new InvalidArgumentException(sprintf('Tag values must be strings, got %s', get_debug_type($value))),
            $values,
        ));
    }

    public function getValue(int $index): ?string
    {
        return $this->values[$index] ?? null;
    }

    public static function tryFromRaw(mixed $raw): ?self
    {
        return is_array($raw) && array_is_list($raw) && array_all($raw, static fn (mixed $value): bool => is_string($value)) ? new self($raw) : null;
    }
}
