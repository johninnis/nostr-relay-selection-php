<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Service;

use Innis\Nostr\RelaySelection\Domain\Collection\FilterCollection;
use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;
use Innis\Nostr\RelaySelection\Domain\Enum\RelayRole;
use Innis\Nostr\RelaySelection\Domain\Enum\Route\ReadBranch;
use Innis\Nostr\RelaySelection\Domain\Failure\NoDmRelaysFailure;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Policy\ReadPolicy;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Route\ReadRoute;
use Innis\Nostr\RelaySelection\Domain\ValueObject\Routing\RelayDirectory;

final class ReadRouter
{
    public static function route(FilterCollection $filters, RelayDirectory $directory, ReadPolicy $policy): ReadRoute|NoDmRelaysFailure
    {
        $pattern = FilterPatternClassifier::classify($filters);
        $recipient = $pattern->getDmRecipient();
        if (null !== $recipient) {
            $relays = $directory->relaysOf($recipient, RelayRole::Dm);

            return $relays->isEmpty() ? NoDmRelaysFailure::NoDmRelays : new ReadRoute(ReadBranch::DmInbox, $relays);
        }
        $user = $policy->getUserPubkey();
        if (ReadBranch::Search === $pattern->getBranch()) {
            return new ReadRoute(ReadBranch::Search, $directory->permitted(
                $directory->relaysOf($user, RelayRole::Search),
                $policy->getCallerRelays(),
            ));
        }

        return new ReadRoute(ReadBranch::General, self::generalRelays($filters, $directory, $policy));
    }

    private static function generalRelays(FilterCollection $filters, RelayDirectory $directory, ReadPolicy $policy): RelaySet
    {
        $tagged = self::taggedOf($filters);
        $taggedInboxes = array_map(
            static fn (PublicKey $pubkey): RelaySet => $directory->relaysOf($pubkey, RelayRole::Inbox),
            $tagged,
        );
        $needsUserRelays = [] === $tagged
            || array_any($filters->toArray(), static fn (Filter $filter): bool => ($filter->getPTags()?->isEmpty() ?? true))
            || array_any($taggedInboxes, static fn (RelaySet $inbox): bool => $inbox->isEmpty());
        $user = $policy->getUserPubkey();
        $userRelays = $needsUserRelays
            ? [$directory->relaysOf($user, RelayRole::Inbox), $directory->relaysOf($user, RelayRole::Outbox)]
            : [];

        return $directory->permitted(...[...$taggedInboxes, ...$userRelays, $policy->getCallerRelays()]);
    }

    /**
     * @return list<PublicKey>
     */
    private static function taggedOf(FilterCollection $filters): array
    {
        $tagged = [];
        foreach ($filters as $filter) {
            foreach ($filter->getPTags() ?? [] as $pubkey) {
                $tagged[$pubkey->toHex()] ??= $pubkey;
            }
        }

        return array_values($tagged);
    }
}
