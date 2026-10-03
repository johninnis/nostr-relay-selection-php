<?php

declare(strict_types=1);

// Deliberate: re-declared rather than taken from nostr-core, hex-only — see ADR-0003

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Identity;

final readonly class PublicKey
{
    private const string HEX_PATTERN = '/^[a-f0-9]{64}$/D';

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

    public static function tryFromHex(string $hex): ?self
    {
        return 1 === preg_match(self::HEX_PATTERN, $hex) ? new self($hex) : null;
    }
}
