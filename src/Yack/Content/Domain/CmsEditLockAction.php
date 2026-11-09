<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** What the builder asks of the edit lock of the content it opened (D13). */
enum CmsEditLockAction: string
{
    /** At the opening: takes the lock when it is free (or already held by the same administrator). */
    case Acquire = 'acquire';

    /** Every CmsEditLockState::HEARTBEAT seconds: sign of life, only with the token received. */
    case Heartbeat = 'heartbeat';

    /** « Prendre la main »: takes the lock from its holder. */
    case Takeover = 'takeover';

    /** At the closing: gives the lock back, only with the token received. */
    case Release = 'release';
}
