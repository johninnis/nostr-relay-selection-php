<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\Collection;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RelaySetTest extends TestCase
{
    private static function url(string $raw): RelayUrl
    {
        return RelayUrl::tryFromString($raw) ?? throw new InvalidArgumentException('Invalid test URL '.$raw);
    }

    public function testConstructionKeepsTheFirstOccurrenceOfEachUrl(): void
    {
        $set = new RelaySet([self::url('wss://b.example.com'), self::url('wss://a.example.com'), self::url('wss://b.example.com/')]);
        $this->assertSame(['wss://b.example.com', 'wss://a.example.com'], $set->toStrings());
    }

    public function testConstructionRejectsANonRelayUrlElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelaySet(['wss://a.example.com']);
    }

    public function testFromStringsDropsMalformedAndNonStringEntries(): void
    {
        $this->assertSame(['wss://a.example.com'], RelaySet::fromStrings(['', 42, null, 'wss://a.example.com'])->toStrings());
    }

    public function testUnionKeepsFirstSeenOrderAcrossSets(): void
    {
        $a = RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com']);
        $b = RelaySet::fromStrings(['wss://b.example.com', 'wss://c.example.com']);
        $this->assertSame(['wss://a.example.com', 'wss://b.example.com', 'wss://c.example.com'], $a->union($b)->toStrings());
    }

    public function testWithoutRemovesBlockedUrls(): void
    {
        $set = RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com']);
        $this->assertSame(['wss://b.example.com'], $set->without(RelaySet::fromStrings(['wss://a.example.com']))->toStrings());
    }

    public function testTakeKeepsTheFirstEntries(): void
    {
        $set = RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com', 'wss://c.example.com']);
        $this->assertSame(['wss://a.example.com', 'wss://b.example.com'], $set->take(2)->toStrings());
    }

    public function testTakeRejectsANegativeCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RelaySet()->take(-1);
    }

    public function testFirstIsNullForAnEmptySet(): void
    {
        $this->assertNull(new RelaySet()->first());
    }

    public function testIteratesAndCountsInOrder(): void
    {
        $set = RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com']);
        $this->assertSame([2, ['wss://a.example.com', 'wss://b.example.com']], [count($set), array_map(strval(...), iterator_to_array($set))]);
    }
}
