<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PerRecipientCap;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PerRecipientCapTest extends TestCase
{
    public function testLimitsToItsCount(): void
    {
        $relays = RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com']);
        $this->assertSame(['wss://a.example.com'], new PerRecipientCap(1)->limit($relays)->toStrings());
    }

    public function testAllKeepsEveryRelay(): void
    {
        $relays = RelaySet::fromStrings(['wss://a.example.com', 'wss://b.example.com']);
        $this->assertSame(['wss://a.example.com', 'wss://b.example.com'], PerRecipientCap::all()->limit($relays)->toStrings());
    }

    public function testRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PerRecipientCap(0);
    }
}
