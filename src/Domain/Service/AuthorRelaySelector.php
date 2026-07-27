<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\AuthorRelaysContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Tag;

final class AuthorRelaySelector
{
    /**
     * @return list<RelayUrl>
     */
    public static function inbox(AuthorRelaysContext $context): array
    {
        return self::select($context, EventKind::RelayList->value, RelayListExtractor::inbox(...));
    }

    /**
     * @return list<RelayUrl>
     */
    public static function outbox(AuthorRelaysContext $context): array
    {
        return self::select($context, EventKind::RelayList->value, RelayListExtractor::outbox(...));
    }

    /**
     * @return list<RelayUrl>
     */
    public static function dm(AuthorRelaysContext $context): array
    {
        return self::select($context, EventKind::DmRelayList->value, RelayListExtractor::dm(...));
    }

    /**
     * @param callable(list<Tag>): array<array-key, mixed> $extract
     *
     * @return list<RelayUrl>
     */
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
