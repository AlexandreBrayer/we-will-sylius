<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedPaymentPage;

/**
 * @implements ProviderInterface<SimulatedPaymentPageView>
 */
final readonly class SimulatedPaymentPageProvider implements ProviderInterface
{
    public function __construct(private SimulatedPaymentPage $page)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?SimulatedPaymentPageView
    {
        $session = $this->page->find(is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '');

        return null === $session ? null : SimulatedPaymentPageView::of($session);
    }
}
