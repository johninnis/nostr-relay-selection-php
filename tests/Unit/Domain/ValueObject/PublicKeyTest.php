<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use PHPUnit\Framework\TestCase;

final class PublicKeyTest extends TestCase
{
    private const string VALID_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testFromHexAcceptsValidLowercaseHex(): void
    {
        $key = PublicKey::tryFromHex(self::VALID_HEX);
        $this->assertNotNull($key);
        $this->assertSame(self::VALID_HEX, $key->toHex());
    }

    public function testFromHexRejectsTooShortHex(): void
    {
        $this->assertNull(PublicKey::tryFromHex(str_repeat('a', 63)));
    }

    public function testFromHexRejectsTooLongHex(): void
    {
        $this->assertNull(PublicKey::tryFromHex(str_repeat('a', 65)));
    }

    public function testFromHexRejectsUppercaseHex(): void
    {
        $this->assertNull(PublicKey::tryFromHex(strtoupper(self::VALID_HEX)));
    }

    public function testFromHexRejectsNonHexCharacters(): void
    {
        $this->assertNull(PublicKey::tryFromHex(str_repeat('z', 64)));
    }

    public function testEqualsReturnsTrueForIdenticalKeys(): void
    {
        $a = PublicKey::tryFromHex(self::VALID_HEX);
        $b = PublicKey::tryFromHex(self::VALID_HEX);
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentKeys(): void
    {
        $a = PublicKey::tryFromHex(self::VALID_HEX);
        $b = PublicKey::tryFromHex(str_repeat('f', 64));
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertFalse($a->equals($b));
    }
}
