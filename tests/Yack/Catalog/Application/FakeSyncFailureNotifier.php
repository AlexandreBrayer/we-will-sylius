<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use App\Entity\Catalog\SyncRun;
use App\Yack\Catalog\Domain\SyncFailureNotifier;

/** Hand-written fake of the port: the address given (null: nobody notified), the runs notified. */
final class FakeSyncFailureNotifier implements SyncFailureNotifier
{
    /** @var list<SyncRun> */
    public array $notified = [];

    public function __construct(private readonly ?string $recipient = 'catalogue@yack.test')
    {
    }

    public function notify(SyncRun $run): ?string
    {
        $this->notified[] = $run;

        return $this->recipient;
    }
}
