<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Failure;

enum NoDmRelaysFailure: string
{
    case NoDmRelays = 'noDmRelays';
}
