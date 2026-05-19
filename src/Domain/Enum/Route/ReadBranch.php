<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Enum\Route;

enum ReadBranch: string
{
    case Search = 'search';
    case DmInbox = 'dmInbox';
    case General = 'general';
}
