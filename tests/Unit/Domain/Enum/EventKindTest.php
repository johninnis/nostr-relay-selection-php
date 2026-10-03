<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Tests\Unit\Domain\Enum;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use PHPUnit\Framework\TestCase;

final class EventKindTest extends TestCase
{
    public function testMetadataIsIndexedButIsNeitherAGiftWrapNorADraft(): void
    {
        $this->assertSame([true, false, false], [EventKind::Metadata->isIndexed(), EventKind::Metadata->isGiftWrap(), EventKind::Metadata->isDraft()]);
    }

    public function testBothNip59GiftWrapKindsAreGiftWraps(): void
    {
        $this->assertSame(
            [true, true, false],
            array_map(static fn (EventKind $kind): bool => $kind->isGiftWrap(), [EventKind::GiftWrap, EventKind::EphemeralGiftWrap, EventKind::Metadata]),
        );
    }

    public function testTheDraftKindsAreDrafts(): void
    {
        $this->assertSame(
            [true, true, true, false],
            array_map(static fn (EventKind $kind): bool => $kind->isDraft(), [EventKind::LongformContentDraft, EventKind::ClassifiedListingDraft, EventKind::DraftEvent, EventKind::Metadata]),
        );
    }

    public function testExactlyTheFollowListTheNip51ListsAndSetsOfPeopleAndTheReportArePubkeyData(): void
    {
        $this->assertSame(
            [
                EventKind::FollowList,
                EventKind::Reporting,
                EventKind::MuteList,
                EventKind::GitAuthorsList,
                EventKind::MediaFollowsList,
                EventKind::FavouritePodcastsList,
                EventKind::AuthoredPodcastsList,
                EventKind::GoodWikiAuthorsList,
                EventKind::FollowSet,
                EventKind::KindMuteSet,
                EventKind::StarterPack,
                EventKind::MediaStarterPack,
            ],
            array_values(array_filter(EventKind::cases(), static fn (EventKind $kind): bool => $kind->isPubkeyData())),
        );
    }
}
