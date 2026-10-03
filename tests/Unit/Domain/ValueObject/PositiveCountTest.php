<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PositiveCount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PositiveCountTest extends TestCase
{
    public function testKeepsAPositiveValue(): void
    {
        $this->assertSame(3, new PositiveCount(3)->getValue());
    }

    public function testRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PositiveCount(0);
    }

    public function testRejectsANegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PositiveCount(-1);
    }
}
