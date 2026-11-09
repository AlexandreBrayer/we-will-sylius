<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedPaymentPage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SimulatedCardInput|SimulatedChallengeInput|null, SimulatedPaymentPageView>
 */
final readonly class SimulatedPaymentPageProcessor implements ProcessorInterface
{
    public const CARD = 'app_yack_api_shop_simulated_payment_page_card';
    public const CHALLENGE = 'app_yack_api_shop_simulated_payment_page_challenge';
    public const CANCEL = 'app_yack_api_shop_simulated_payment_page_cancel';

    public function __construct(private SimulatedPaymentPage $page)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SimulatedPaymentPageView
    {
        $session = $this->page->find(is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '')
            ?? throw new NotFoundHttpException('Session de paiement inconnue ou expirée.');

        try {
            match (true) {
                $data instanceof SimulatedCardInput => $this->page->submitCard($session, $data->card(), $data->saveCard),
                $data instanceof SimulatedChallengeInput => $this->page->answerChallenge($session, (string) $data->code),
                self::CANCEL === $operation->getName() => $this->page->cancel($session),
                default => throw new \LogicException(sprintf('Unexpected operation "%s".', (string) $operation->getName())),
            };
        } catch (\DomainException $wrongStep) {
            throw new ConflictHttpException($wrongStep->getMessage(), $wrongStep);
        }

        return SimulatedPaymentPageView::of($session);
    }
}
