<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\Enum;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use PHPUnit\Framework\TestCase;

final class RelayRoleTest extends TestCase
{
    public function testEachRoleNamesTheKindItIsReadFrom(): void
    {
        $this->assertSame(
            [EventKind::RelayList, EventKind::RelayList, EventKind::DmRelayList, EventKind::SearchRelaysList, EventKind::BlockedRelaysList],
            array_map(static fn (RelayRole $role): EventKind => $role->kind(), RelayRole::cases()),
        );
    }
}
