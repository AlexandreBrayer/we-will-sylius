<?php

declare(strict_types=1);

namespace App\Tests\Yack\Catalog\Application;

use Psr\Log\AbstractLogger;

/** Keeps the log messages in memory, with their level. */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    /** @var list<string> level of each message */
    public array $levels = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
        $this->levels[] = is_string($level) ? $level : '';
    }
}
