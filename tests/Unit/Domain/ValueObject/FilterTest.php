<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    public function testConstructorRejectsANonIntegerKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Filter(['1']);
    }

    public function testAnEmptySearchIsNotASearch(): void
    {
        $this->assertFalse(new Filter(search: '')->hasSearch());
    }

    public function testANonEmptySearchIsASearch(): void
    {
        $this->assertTrue(new Filter(search: 'nostr')->hasSearch());
    }

    public function testTryFromRawKeepsKindsInOrder(): void
    {
        $this->assertSame([7, 1], Filter::tryFromRaw(['kinds' => [7, 1]])?->getKinds());
    }
}
