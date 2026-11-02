<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Twig;

use App\Yack\Account\Domain\CustomerProfile;
use App\Yack\Account\Infrastructure\Security\CustomerProfileResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CustomerProfileExtension extends AbstractExtension
{
    public function __construct(private readonly CustomerProfileResolver $profileResolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_customer_profile', $this->currentProfile(...)),
        ];
    }

    public function currentProfile(): CustomerProfile
    {
        return $this->profileResolver->current();
    }
}
