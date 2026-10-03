<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\ValueObject\Policy;

use Innis\Nostr\RelaySelection\Domain\Collection\RelaySet;

final readonly class AuthorReadPolicy
{
    public const int DEFAULT_MAX_AUTHORS_PER_FILTER = 200;

    public function __construct(
        private RelaySet $fallbackRelays = new RelaySet(),
        private PositiveCount $maxAuthorsPerFilter = new PositiveCount(self::DEFAULT_MAX_AUTHORS_PER_FILTER),
        private Redundancy $redundancy = new Redundancy(Redundancy::DEFAULT),
    ) {
    }

    public function getFallbackRelays(): RelaySet
    {
        return $this->fallbackRelays;
    }

    public function getMaxAuthorsPerFilter(): PositiveCount
    {
        return $this->maxAuthorsPerFilter;
    }

    public function getRedundancy(): Redundancy
    {
        return $this->redundancy;
    }
}
