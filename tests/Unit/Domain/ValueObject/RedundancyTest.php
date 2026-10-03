<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\Redundancy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RedundancyTest extends TestCase
{
    public function testIsReachedAtItsCount(): void
    {
        $redundancy = new Redundancy(2);
        $this->assertSame([false, true], [$redundancy->isReachedAt(1), $redundancy->isReachedAt(2)]);
    }

    public function testAllIsNeverReached(): void
    {
        $this->assertFalse(Redundancy::all()->isReachedAt(1_000_000));
    }

    public function testRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Redundancy(0);
    }
}
