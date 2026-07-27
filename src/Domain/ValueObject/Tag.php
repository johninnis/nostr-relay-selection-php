<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core — see ADR-0002

namespace Innis\Nostr\RelaySelection\Domain\ValueObject;

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
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Tag values must be strings');
            }
        }
        $this->values = array_values($values);
    }

    public function getValue(int $index): ?string
    {
        return $this->values[$index] ?? null;
    }

    public static function tryFromRaw(mixed $raw): ?self
    {
        if (!is_array($raw)) {
            return null;
        }
        $values = [];
        foreach ($raw as $value) {
            if (!is_string($value)) {
                return null;
            }
            $values[] = $value;
        }

        return new self($values);
    }
}
