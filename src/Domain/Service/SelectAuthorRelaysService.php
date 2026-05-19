<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorRelaysContext;

final class SelectAuthorRelaysService
{
    public static function inbox(AuthorRelaysContext $context): array
    {
        return self::select($context, EventKind::RelayList->value, RelayListExtractor::inbox(...));
    }

    public static function outbox(AuthorRelaysContext $context): array
    {
        return self::select($context, EventKind::RelayList->value, RelayListExtractor::outbox(...));
    }

    public static function dm(AuthorRelaysContext $context): array
    {
        return self::select($context, EventKind::DmRelayList->value, RelayListExtractor::dm(...));
    }

    private static function select(AuthorRelaysContext $context, int $kind, callable $extract): array
    {
        $list = EventSelector::newestByPubkeyAndKind(
            $context->getRelayListEvents(),
            $context->getAuthorPubkey(),
            $kind,
        );
        if (null === $list) {
            return [];
        }

        return RelaySetBuilder::subtract(
            RelaySetBuilder::build($extract($list->getTags())),
            $context->getBlockedRelays(),
        );
    }
}
