<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\EventId;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    public function testTryFromHexAcceptsSixtyFourLowercaseHexCharacters(): void
    {
        $this->assertSame(str_repeat('f', 64), EventId::tryFromHex(str_repeat('f', 64))?->toHex());
    }

    public function testTryFromHexRejectsUppercaseHex(): void
    {
        $this->assertNull(EventId::tryFromHex(str_repeat('F', 64)));
    }

    public function testTryFromHexRejectsShortHex(): void
    {
        $this->assertNull(EventId::tryFromHex(str_repeat('f', 63)));
    }

    public function testTryFromHexRejectsATrailingNewline(): void
    {
        $this->assertNull(EventId::tryFromHex(str_repeat('f', 64)."\n"));
    }

    public function testIsLowerThanComparesLexically(): void
    {
        $low = EventId::tryFromHex(str_repeat('0', 63).'1');
        $high = EventId::tryFromHex(str_repeat('0', 63).'2');
        $this->assertNotNull($low);
        $this->assertNotNull($high);
        $this->assertSame([true, false], [$low->isLowerThan($high), $high->isLowerThan($low)]);
    }

    public function testEqualsComparesHex(): void
    {
        $a = EventId::tryFromHex(str_repeat('a', 64));
        $b = EventId::tryFromHex(str_repeat('a', 64));
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertTrue($a->equals($b));
    }
}
