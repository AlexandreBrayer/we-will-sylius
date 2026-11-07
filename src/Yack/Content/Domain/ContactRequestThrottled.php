<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Too many contact messages from the same sender: the message is not sent.
 */
final class ContactRequestThrottled extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Trop de messages envoyés depuis votre connexion : réessayez dans quelques minutes ou appelez votre agence Yack.');
    }
}
