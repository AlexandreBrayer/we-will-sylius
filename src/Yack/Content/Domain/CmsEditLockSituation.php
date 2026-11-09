<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** Edit lock of a target seen by one administrator (D13). */
enum CmsEditLockSituation: string
{
    /** Nobody holds it: never taken, given back, or without a sign of life for CmsEditLockState::TIMEOUT seconds. */
    case Free = 'free';

    /** Held by the administrator who asks: he edits the content. */
    case Mine = 'mine';

    /** Held by another administrator: the content is read-only, « Prendre la main » is offered. */
    case Other = 'other';
}
