<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Tag;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function testConstructorRejectsANonStringValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Tag(['p', 42]);
    }

    public function testGetValueReturnsNullPastTheLastValue(): void
    {
        $this->assertNull(new Tag(['p'])->getValue(1));
    }

    public function testTryFromRawReturnsNullForANonStringValue(): void
    {
        $this->assertNull(Tag::tryFromRaw(['p', 42]));
    }

    public function testTryFromRawReturnsNullForANonArray(): void
    {
        $this->assertNull(Tag::tryFromRaw('p'));
    }
}
