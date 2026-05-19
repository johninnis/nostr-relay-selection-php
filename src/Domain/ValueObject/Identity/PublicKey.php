<?php

declare(strict_types=1);

// Duplicate of innis/nostr-core's PublicKey value object. innis/nostr-relay-selection
// must not depend on innis/nostr-core, so the type is re-declared here. Only the
// hex form is supported (no bech32) because relay selection never needs the
// encoded form.

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Identity;

final readonly class PublicKey
{
    private const HEX_LENGTH = 64;

    private function __construct(private string $hex)
    {
    }

    public function toHex(): string
    {
        return $this->hex;
    }

    public function equals(self $other): bool
    {
        return $this->hex === $other->hex;
    }

    public function __toString(): string
    {
        return $this->hex;
    }

    public static function fromHex(string $hex): ?self
    {
        if (1 !== preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }
}
