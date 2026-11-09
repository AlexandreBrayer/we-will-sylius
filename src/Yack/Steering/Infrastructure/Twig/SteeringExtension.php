<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Twig;

use App\Yack\Steering\Domain\Wording;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Amounts of the « Direction » pages: whole euros (« 58 420 € ») and short amounts (« 21,4 k€ »).
 */
final class SteeringExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('yack_euros', Wording::euros(...)),
            new TwigFilter('yack_short_euros', Wording::shortEuros(...)),
        ];
    }
}
