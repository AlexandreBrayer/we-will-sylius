<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

enum SyncOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Disabled = 'disabled';
    case Failed = 'failed';
}
