<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

/**
 * Command message: mirror a Yack catalog batch into the shop.
 * Handled synchronously today; routing it to an async transport (RabbitMQ) is configuration only.
 */
final readonly class SynchronizeCatalog
{
    public function __construct(public string $feed)
    {
    }
}
