<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Enum;

enum EventKind: int
{
    case ProfileMetadata = 0;
    case ShortNote = 1;
    case FollowList = 3;
    case Repost = 6;
    case Reaction = 7;
    case GenericRepost = 16;
    case PublicMessage = 24;
    case GiftWrap = 1059;
    case Comment = 1111;
    case Highlight = 9802;
    case RelayList = 10002;
    case BlockedRelayList = 10006;
    case SearchRelayList = 10007;
    case DmRelayList = 10050;
    case LongformDraft = 30024;
    case ClassifiedListingDraft = 30403;
    case DraftEvent = 31234;

    public static function isInboxFanout(int $kind): bool
    {
        return match (self::tryFrom($kind)) {
            self::ShortNote,
            self::Repost,
            self::Reaction,
            self::GenericRepost,
            self::PublicMessage,
            self::Comment,
            self::Highlight => true,
            default => false,
        };
    }

    public static function isDraft(int $kind): bool
    {
        return match (self::tryFrom($kind)) {
            self::LongformDraft,
            self::ClassifiedListingDraft,
            self::DraftEvent => true,
            default => false,
        };
    }

    public static function isIndexed(int $kind): bool
    {
        return match (self::tryFrom($kind)) {
            self::ProfileMetadata,
            self::FollowList,
            self::RelayList,
            self::DmRelayList => true,
            default => false,
        };
    }
}
