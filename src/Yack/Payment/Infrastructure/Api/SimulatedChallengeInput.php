<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Code typed on the simulated 3-D Secure page.
 */
final class SimulatedChallengeInput
{
    #[Assert\NotBlank(message: 'Saisissez le code reçu par SMS.')]
    #[Assert\Length(max: 12)]
    public ?string $code = null;
}
