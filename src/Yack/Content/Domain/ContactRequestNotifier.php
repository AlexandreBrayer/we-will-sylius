<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Port: hands a contact message over to Yack (e-mail to the address configured in config/packages/yack.yaml).
 */
interface ContactRequestNotifier
{
    public function notify(ContactRequest $request): void;
}
