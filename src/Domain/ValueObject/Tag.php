<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Tag
{
    private array $values;

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

    public static function fromRaw(mixed $raw): ?self
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
