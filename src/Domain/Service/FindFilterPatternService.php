<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Entity\Filter;
use Innis\Nostr\RelaySelection\Domain\Enum\EventKind;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;

final class FindFilterPatternService
{
    public static function classify(array $filters): ReadBranch
    {
        if (self::hasSearchFilter($filters)) {
            return ReadBranch::Search;
        }

        if (null !== self::sharedGiftWrapRecipient($filters)) {
            return ReadBranch::DmInbox;
        }

        return ReadBranch::General;
    }

    public static function sharedGiftWrapRecipient(array $filters): ?PublicKey
    {
        if ([] === $filters) {
            return null;
        }

        $shared = null;
        foreach ($filters as $filter) {
            if (!$filter instanceof Filter) {
                return null;
            }
            $kinds = $filter->getKinds();
            if (null === $kinds || 1 !== count($kinds) || EventKind::GiftWrap->value !== $kinds[0]) {
                return null;
            }
            $pTags = $filter->getPTags();
            if (null === $pTags || 1 !== count($pTags)) {
                return null;
            }
            $pubkey = $pTags[0];
            if (null === $shared) {
                $shared = $pubkey;
            } elseif (!$shared->equals($pubkey)) {
                return null;
            }
        }

        return $shared;
    }

    private static function hasSearchFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter instanceof Filter && $filter->hasSearch()) {
                return true;
            }
        }

        return false;
    }
}
