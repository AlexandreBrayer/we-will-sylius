<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api\Registration;

final readonly class RegistrationResult
{
    public function __construct(
        public string $email,
        public string $profile,
        /** The account stays disabled until the e-mail link is followed. */
        public bool $activationRequired = true,
    ) {
    }
}
