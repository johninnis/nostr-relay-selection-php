<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TypedCollectionTest extends TestCase
{
    public function testRejectsAnElementOfTheWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PublicKeyCollection([str_repeat('a', 64)]);
    }

    public function testKeepsElementsInOrder(): void
    {
        $a = PublicKey::tryFromHex(str_repeat('a', 64));
        $b = PublicKey::tryFromHex(str_repeat('b', 64));
        $collection = new PublicKeyCollection([$b, $a]);
        $this->assertSame([$b, $a], $collection->toArray());
    }

    public function testCountsAndIterates(): void
    {
        $collection = new PublicKeyCollection([PublicKey::tryFromHex(str_repeat('a', 64))]);
        $this->assertSame([1, 1, false], [count($collection), count(iterator_to_array($collection)), $collection->isEmpty()]);
    }

    public function testAnEmptyCollectionIsEmpty(): void
    {
        $this->assertTrue(new PublicKeyCollection()->isEmpty());
    }
}
