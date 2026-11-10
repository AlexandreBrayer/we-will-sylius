<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

use App\Entity\Catalog\SyncRun;

/** Port: tells the catalog administrators that a synchronisation failed. */
interface SyncFailureNotifier
{
    /** @return string|null address notified, null when nobody was */
    public function notify(SyncRun $run): ?string;
}
