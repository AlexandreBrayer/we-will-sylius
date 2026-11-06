<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\OrderProcessing\EscompteProcessor;
use App\Yack\Payment\Application\OrderPaymentInstruction;
use App\Yack\Payment\Domain\PaymentInstruction;
use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Shop API cart / order: adds a "yack" block with what the Yack checkout and order pages show —
 * totals breakdown (goods excl. tax, escompte, shipping, VAT, total), chosen payment method with
 * its escompte, payment instruction (RIB + reference + due date, or card accepted) and the
 * payment / delivery follow-up (states, labels, tracking numbers).
 */
final class YackOrderNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'yack_order_normalizer_already_called';

    private const GROUPS = ['sylius:shop:cart:show', 'sylius:shop:order:index', 'sylius:shop:order:account:show'];

    private const PAYMENT_STATE_LABELS = [
        'cart' => 'Panier',
        'awaiting_payment' => 'En attente',
        'partially_authorized' => 'Partiellement autorisée',
        'authorized' => 'Autorisée',
        'partially_paid' => 'Partiellement payée',
        'paid' => 'Payée',
        'partially_refunded' => 'Partiellement remboursée',
        'refunded' => 'Remboursée',
        'cancelled' => 'Annulée',
    ];

    private const SHIPPING_STATE_LABELS = [
        'cart' => 'Panier',
        'ready' => 'En préparation',
        'partially_shipped' => 'Partiellement expédiée',
        'shipped' => 'Expédiée',
        'cancelled' => 'Annulée',
    ];

    public function __construct(
        #[Autowire(service: 'sylius.section_resolver.uri_based')]
        private readonly SectionProviderInterface $sectionProvider,
        private readonly OrderPaymentInstruction $orderPaymentInstruction,
        private readonly PaymentMethodEscompteRates $escompteRates,
    ) {
    }

    /** @return array<string, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        \assert($data instanceof OrderInterface);
        $context[self::ALREADY_CALLED] = true;

        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (!is_array($normalized)) {
            return $normalized;
        }

        $instruction = $this->orderPaymentInstruction->forOrder($data);
        $method = $data->getLastPayment()?->getMethod();

        $normalized['yack'] = [
            'totals' => self::totals($data),
            'escompte' => self::escompte($data),
            'paymentMethod' => $method instanceof PaymentMethodInterface ? $this->paymentMethod($method) : null,
            'paymentInstruction' => null === $instruction ? null : self::instruction($instruction, $data),
            'paymentStatus' => [
                'state' => $data->getPaymentState(),
                'label' => 'awaiting_payment' === $data->getPaymentState() && true === $instruction?->isBankTransfer()
                    ? 'Virement attendu'
                    : (self::PAYMENT_STATE_LABELS[$data->getPaymentState()] ?? $data->getPaymentState()),
            ],
            'shippingStatus' => [
                'state' => $data->getShippingState(),
                'label' => self::SHIPPING_STATE_LABELS[$data->getShippingState()] ?? $data->getShippingState(),
            ],
            'shipments' => array_values(array_map(static fn (ShipmentInterface $shipment): array => [
                'id' => $shipment->getId(),
                'method' => null === $shipment->getMethod() ? null : ['code' => $shipment->getMethod()->getCode(), 'name' => $shipment->getMethod()->getName()],
                'state' => $shipment->getState(),
                'tracking' => $shipment->getTracking(),
                'shippedAt' => $shipment->getShippedAt()?->format(\DateTimeInterface::ATOM),
            ], $data->getShipments()->toArray())),
        ];

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED]) || !$data instanceof OrderInterface) {
            return false;
        }

        if (!$this->sectionProvider->getSection() instanceof ShopApiSection) {
            return false;
        }

        $groups = array_filter((array) ($context['groups'] ?? []), is_string(...));

        return [] !== array_intersect(self::GROUPS, $groups);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [OrderInterface::class => false];
    }

    /**
     * All amounts in cents. VAT is computed on the goods after escompte, and on shipping at
     * the product rate.
     *
     * @return array<string, int>
     */
    private static function totals(OrderInterface $order): array
    {
        $itemsGross = 0;
        $itemsTax = 0;
        foreach ($order->getItems() as $item) {
            $itemsGross += $item->getUnitPrice() * $item->getQuantity();
            $itemsTax += $item->getTaxTotal();
        }

        $shipping = $order->getAdjustmentsTotal(AdjustmentInterface::SHIPPING_ADJUSTMENT)
            + $order->getAdjustmentsTotal(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT);

        return [
            'itemsExclTax' => $itemsGross,
            'promotion' => $order->getOrderPromotionTotal(),
            'escompte' => $order->getAdjustmentsTotalRecursively(EscompteProcessor::ESCOMPTE_ADJUSTMENT),
            'itemsNetExclTax' => $order->getItemsTotal() - $itemsTax,
            'shippingExclTax' => $shipping,
            'itemsTax' => $itemsTax,
            'shippingTax' => $order->getTaxTotal() - $itemsTax,
            'taxTotal' => $order->getTaxTotal(),
            'totalExclTax' => $order->getTotal() - $order->getTaxTotal(),
            'total' => $order->getTotal(),
        ];
    }

    /** @return array{label: string, amount: int}|null */
    private static function escompte(OrderInterface $order): ?array
    {
        $adjustments = $order->getAdjustmentsRecursively(EscompteProcessor::ESCOMPTE_ADJUSTMENT);
        $first = $adjustments->first();
        if (false === $first) {
            return null;
        }

        return [
            'label' => (string) $first->getLabel(),
            'amount' => $order->getAdjustmentsTotalRecursively(EscompteProcessor::ESCOMPTE_ADJUSTMENT),
        ];
    }

    /** @return array{code: string|null, name: string|null, escompteRate: float|null, escompteLabel: string|null} */
    private function paymentMethod(PaymentMethodInterface $method): array
    {
        $code = (string) $method->getCode();

        return [
            'code' => $method->getCode(),
            'name' => $method->getName(),
            'escompteRate' => $this->escompteRates->forPaymentMethod($code),
            'escompteLabel' => $this->escompteRates->labelFor($code),
        ];
    }

    /** @return array<string, mixed> */
    private static function instruction(PaymentInstruction $instruction, OrderInterface $order): array
    {
        return [
            'kind' => $instruction->kind->value,
            'amount' => $order->getTotal(),
            'paid' => PaymentInterface::STATE_COMPLETED === $order->getLastPayment()?->getState(),
            'bankAccount' => null === $instruction->bankAccount ? null : [
                'holder' => $instruction->bankAccount->holder,
                'bank' => $instruction->bankAccount->bank,
                'iban' => $instruction->bankAccount->iban,
                'bic' => $instruction->bankAccount->bic,
            ],
            'reference' => $instruction->reference,
            'dueDate' => $instruction->dueDate?->format('Y-m-d'),
        ];
    }
}
