<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Context\ReadContext;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\ReadRoute;

final class ReadRouter
{
    public static function route(ReadContext $context): ReadRoute
    {
        $branch = FilterPatternClassifier::classify($context->getFilters());
        if (ReadBranch::DmInbox === $branch) {
            $relays = self::dmInboxRelays(
                $context,
                FilterPatternClassifier::sharedGiftWrapRecipient($context->getFilters()),
            );
            if (null === $relays) {
                return new ReadRoute($branch, null);
            }
            $afterBlock = RelaySetBuilder::subtract($relays, $context->getBlockedRelays());

            return new ReadRoute($branch, [] === $afterBlock ? null : $afterBlock);
        }

        $relays = match ($branch) {
            ReadBranch::Search => RelaySetBuilder::build($context->getSearchRelays(), $context->getCallerRelays()),
            ReadBranch::General => RelaySetBuilder::build(
                $context->getUserRelayUrls(),
                $context->getCallerRelays(),
            ),
        };

        return new ReadRoute($branch, RelaySetBuilder::subtract($relays, $context->getBlockedRelays()));
    }

    /**
     * @return ?list<RelayUrl>
     */
    private static function dmInboxRelays(ReadContext $context, ?PublicKey $recipient): ?array
    {
        if (null === $recipient) {
            return null;
        }
        $list = EventSelector::newestByPubkeyAndKind(
            $context->getRelayListEvents(),
            $recipient,
            EventKind::DmRelayList->value,
        );
        if (null === $list) {
            return null;
        }
        $relays = RelaySetBuilder::build(RelayListExtractor::dm($list->getTags()));

        return [] === $relays ? null : $relays;
    }
}
