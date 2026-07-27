<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core, hex-only — see ADR-0002

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Identity;

use Override;

final readonly class PublicKey
{
    private const int HEX_LENGTH = 64;

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

    #[Override]
    public function __toString(): string
    {
        return $this->hex;
    }

    public static function tryFromHex(string $hex): ?self
    {
        if (1 !== preg_match('/^[a-f0-9]{'.self::HEX_LENGTH.'}$/', $hex)) {
            return null;
        }

        return new self($hex);
    }
}
