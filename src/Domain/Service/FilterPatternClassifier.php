<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\FilterCollection;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\FilterPattern;

final class FilterPatternClassifier
{
    public static function classify(FilterCollection $filters): FilterPattern
    {
        if (array_any($filters->toArray(), static fn (Filter $filter): bool => $filter->hasSearch())) {
            return FilterPattern::search();
        }
        $recipient = self::sharedGiftWrapRecipient($filters);

        return null === $recipient ? FilterPattern::general() : FilterPattern::dmInbox($recipient);
    }

    private static function sharedGiftWrapRecipient(FilterCollection $filters): ?PublicKey
    {
        $recipients = array_map(self::giftWrapRecipientOf(...), $filters->toArray());
        $first = $recipients[0] ?? null;
        if (null === $first) {
            return null;
        }

        return array_all($recipients, static fn (?PublicKey $recipient): bool => null !== $recipient && $recipient->equals($first)) ? $first : null;
    }

    private static function giftWrapRecipientOf(Filter $filter): ?PublicKey
    {
        $recipients = $filter->getPTags()?->toArray() ?? [];
        $kinds = $filter->getKinds() ?? [];
        $isGiftWrapOnly = [] !== $kinds && array_all($kinds, static fn (int $kind): bool => EventKind::tryFrom($kind)?->isGiftWrap() ?? false);

        return $isGiftWrapOnly && 1 === count($recipients) ? $recipients[0] : null;
    }
}
