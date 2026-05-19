<?php

declare(strict_types=1);

namespace Innis\Nostr\RelaySelection\Domain\Enum\Route;

enum PublishBranch: string
{
    case General = 'general';
    case Dm = 'dm';
    case Draft = 'draft';
}
