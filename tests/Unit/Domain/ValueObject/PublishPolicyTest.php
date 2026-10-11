<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\ValueObject;

use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PerRecipientCap;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\PublishPolicy;
use PHPUnit\Framework\TestCase;

final class PublishPolicyTest extends TestCase
{
    public function testDefaultsToTheCapThatKeepsEveryInboxRelay(): void
    {
        $this->assertEquals(PerRecipientCap::all(), new PublishPolicy()->getPerRecipientCap());
    }

    public function testReadsANullCapAsTheCapThatKeepsEveryInboxRelay(): void
    {
        $this->assertEquals(PerRecipientCap::all(), new PublishPolicy(perRecipientCap: null)->getPerRecipientCap());
    }

    public function testKeepsTheCapItIsGiven(): void
    {
        $cap = new PerRecipientCap(3);
        $this->assertSame($cap, new PublishPolicy(perRecipientCap: $cap)->getPerRecipientCap());
    }
}
