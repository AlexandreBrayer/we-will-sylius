<?php

declare(strict_types=1);

namespace App\Yack\Steering\Infrastructure\Twig;

use App\Yack\Steering\Application\AlertBoard;
use App\Yack\Steering\Application\Pilotage;
use App\Yack\Steering\Application\PilotageReport;
use App\Yack\Steering\Domain\Alerts;
use App\Yack\Steering\Domain\Wording;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Dashboard tabs: amounts in euros (« 58 420 € », « 21,4 k€ »), and the Pilotage report and alerts read by the hooks
 * of the Sylius dashboard (figures, optional row, tab badges). The templates call them only after
 * is_granted('YACK_STEERING').
 */
final class SteeringExtension extends AbstractExtension
{
    public function __construct(
        private readonly PilotageReport $pilotage,
        private readonly AlertBoard $alerts,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('yack_euros', Wording::euros(...)),
            new TwigFilter('yack_short_euros', Wording::shortEuros(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yack_steering_pilotage', $this->pilotage(...)),
            new TwigFunction('yack_steering_alerts', $this->alerts(...)),
        ];
    }

    public function pilotage(): Pilotage
    {
        return $this->pilotage->build();
    }

    public function alerts(): Alerts
    {
        return $this->alerts->alerts();
    }
}
