<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Enum;

enum EventKind: int
{
    case Metadata = 0;
    case FollowList = 3;
    case GiftWrap = 1059;
    case Reporting = 1984;
    case MuteList = 10000;
    case RelayList = 10002;
    case BlockedRelaysList = 10006;
    case SearchRelaysList = 10007;
    case GitAuthorsList = 10017;
    case MediaFollowsList = 10020;
    case DmRelayList = 10050;
    case FavouritePodcastsList = 10054;
    case AuthoredPodcastsList = 10064;
    case GoodWikiAuthorsList = 10101;
    case EphemeralGiftWrap = 21059;
    case FollowSet = 30000;
    case KindMuteSet = 30007;
    case LongformContentDraft = 30024;
    case ClassifiedListingDraft = 30403;
    case DraftEvent = 31234;
    case StarterPack = 39089;
    case MediaStarterPack = 39092;

    public function isGiftWrap(): bool
    {
        return match ($this) {
            self::GiftWrap,
            self::EphemeralGiftWrap => true,
            default => false,
        };
    }

    public function isDraft(): bool
    {
        return match ($this) {
            self::LongformContentDraft,
            self::ClassifiedListingDraft,
            self::DraftEvent => true,
            default => false,
        };
    }

    public function isIndexed(): bool
    {
        return match ($this) {
            self::Metadata,
            self::FollowList,
            self::RelayList,
            self::DmRelayList => true,
            default => false,
        };
    }

    public function isPubkeyData(): bool
    {
        return match ($this) {
            self::FollowList,
            self::Reporting,
            self::MuteList,
            self::GitAuthorsList,
            self::MediaFollowsList,
            self::FavouritePodcastsList,
            self::AuthoredPodcastsList,
            self::GoodWikiAuthorsList,
            self::FollowSet,
            self::KindMuteSet,
            self::StarterPack,
            self::MediaStarterPack => true,
            default => false,
        };
    }
}
