<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Port: limit of contact messages per sender (IP address) over a time window.
 */
interface ContactRequestThrottle
{
    /** Counts one message for `$sender`; false when the limit is already reached (nothing counted). */
    public function tryConsume(string $sender): bool;
}
