<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use App\OrderProcessing\EscompteProcessor;
use App\Yack\Catalog\Application\ProductKindResolverInterface;
use App\Yack\Payment\Application\Card\PayableOrder;
use App\Yack\Payment\Application\OrderPaymentInstruction;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\PaymentInstruction;
use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use App\Yack\Payment\Domain\PaymentTerms;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Shop API cart / order: adds a "yack" block with what the Yack checkout and order pages show —
 * totals breakdown (goods excl. tax, escompte, shipping, VAT, total), chosen payment method with
 * its escompte, payment instruction (RIB + reference + due date, or card paid with its masked
 * card), payment still to make (retry by card or switch to a bank transfer), payment history
 * (attempts with their outcome) and the delivery follow-up (states, labels, tracking numbers).
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

    /** States of one Sylius payment (history of the attempts). */
    private const PAYMENT_LABELS = [
        'cart' => 'Panier',
        'new' => 'En attente',
        'processing' => 'En cours',
        'authorized' => 'Autorisé',
        'completed' => 'Payé',
        'failed' => 'Refusé',
        'cancelled' => 'Abandonné',
        'refunded' => 'Remboursé',
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
        private readonly PaymentTerms $paymentTerms,
        private readonly CacheManager $imagineCache,
        private readonly ProductKindResolverInterface $productKindResolver,
        /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface> */
        #[Autowire(service: 'sylius.repository.payment_method')]
        private readonly PaymentMethodRepositoryInterface $paymentMethods,
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
        $placed = null !== $data->getNumber();

        $normalized['yack'] = [
            'totals' => self::totals($data),
            'escompte' => self::escompte($data),
            'paymentMethod' => $method instanceof PaymentMethodInterface ? $this->paymentMethod($method) : null,
            'paymentInstruction' => null === $instruction ? null : self::instruction($instruction, $data),
            'paymentStatus' => [
                'state' => $data->getPaymentState(),
                'label' => self::paymentStateLabel($data->getPaymentState(), $instruction),
            ],
            'payable' => $placed ? $this->payable($data, $instruction) : null,
            'payments' => $placed ? self::payments($data) : [],
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
            'items' => array_values(array_map($this->item(...), $data->getItems()->toArray())),
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

    private static function paymentStateLabel(?string $state, ?PaymentInstruction $instruction): ?string
    {
        if ('awaiting_payment' === $state && null !== $instruction) {
            return $instruction->isBankTransfer() ? 'Virement attendu' : 'Paiement carte en attente';
        }

        return self::PAYMENT_STATE_LABELS[(string) $state] ?? $state;
    }

    /**
     * Payment still to make on a placed order (retry by card, or switch to a bank transfer), with
     * the outcome of the last card attempt when it failed and the card is still the method, and
     * the methods of the channel (with their escompte); null once paid or cancelled.
     *
     * @return array<string, mixed>|null
     */
    private function payable(OrderInterface $order, ?PaymentInstruction $instruction): ?array
    {
        $payment = PayableOrder::pendingPayment($order);
        if (null === $payment) {
            return null;
        }

        $lastAttempt = null;
        $attempts = true === $instruction?->isBankTransfer() ? [] : array_reverse($order->getPayments()->toArray());
        foreach ($attempts as $previous) {
            $attempt = CardPaymentAttempt::fromDetails($previous->getDetails());
            if (null !== $attempt) {
                $lastAttempt = $attempt->status->isConcluded() ? self::attempt($attempt) : null;
                break;
            }
        }

        $methods = [];
        $channel = $order->getChannel();
        foreach (null === $channel ? [] : $this->paymentMethods->findEnabledForChannel($channel) as $method) {
            $methods[] = $this->paymentMethod($method);
        }

        return [
            'paymentId' => $payment->getId(),
            'amount' => $payment->getAmount(),
            'methodCode' => $payment->getMethod()?->getCode(),
            'lastAttempt' => $lastAttempt,
            'methods' => $methods,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function payments(OrderInterface $order): array
    {
        $payments = [];
        foreach ($order->getPayments() as $payment) {
            $payments[] = self::payment($payment);
        }

        return $payments;
    }

    /** @return array<string, mixed> */
    private static function payment(BasePaymentInterface $payment): array
    {
        $attempt = CardPaymentAttempt::fromDetails($payment->getDetails());

        return [
            'id' => $payment->getId(),
            'method' => $payment->getMethod()?->getName(),
            'state' => $payment->getState(),
            'stateLabel' => self::PAYMENT_LABELS[$payment->getState()] ?? $payment->getState(),
            'amount' => $payment->getAmount(),
            'createdAt' => $payment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $payment->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'card' => null === $attempt ? null : self::attempt($attempt),
        ];
    }

    /** @return array<string, mixed> */
    private static function attempt(CardPaymentAttempt $attempt): array
    {
        return [
            'status' => $attempt->status->value,
            'message' => $attempt->message(),
            'reason' => $attempt->refusalReason,
            'card' => CardView::of($attempt->card),
            'threeDSecure' => $attempt->threeDSecure,
            'oneClick' => $attempt->oneClick,
            'transactionReference' => $attempt->transactionReference,
            'concludedAt' => $attempt->concludedAt?->format(\DateTimeInterface::ATOM),
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

    /** @return array{code: string|null, name: string|null, escompteRate: float|null, escompteLabel: string|null, dueDays: int|null, card: bool} */
    private function paymentMethod(PaymentMethodInterface $method): array
    {
        $code = (string) $method->getCode();

        return [
            'code' => $method->getCode(),
            'name' => $method->getName(),
            'escompteRate' => $this->escompteRates->forPaymentMethod($code),
            'escompteLabel' => $this->escompteRates->labelFor($code),
            'dueDays' => $this->paymentTerms->dueDays($code),
            // Paid on the provider's hosted page (ADR 0009).
            'card' => $this->paymentTerms->isCard($code),
        ];
    }

    /**
     * Catalogue data of a line that the native item lacks (it only has the variant IRI and the
     * product name): product code / slug for the link, thumbnail, finished product or spare part.
     *
     * @return array{id: mixed, variantCode: string|null, productCode: string|null, productSlug: string|null, image: string|null, type: string|null, typeLabel: string|null}
     */
    private function item(OrderItemInterface $item): array
    {
        $product = $item->getProduct();
        $image = $product?->getImagesByType('main')->first() ?: $product?->getImages()->first();
        $kind = null === $product ? null : $this->productKindResolver->kindOf($product);

        return [
            'id' => $item->getId(),
            'variantCode' => $item->getVariant()?->getCode(),
            'productCode' => $product?->getCode(),
            'productSlug' => $product?->getSlug(),
            'image' => $image instanceof ImageInterface && null !== $image->getPath()
                ? $this->imagineCache->getBrowserPath($image->getPath(), 'sylius_shop_product_thumbnail')
                : null,
            'type' => $kind?->apiType(),
            'typeLabel' => $kind?->label(),
        ];
    }

    /** @return array<string, mixed> */
    private static function instruction(PaymentInstruction $instruction, OrderInterface $order): array
    {
        $paid = $order->getLastPayment(PaymentInterface::STATE_COMPLETED);
        $cardAttempt = null === $paid ? null : CardPaymentAttempt::fromDetails($paid->getDetails());

        return [
            'kind' => $instruction->kind->value,
            'amount' => $order->getTotal(),
            'paid' => null !== $paid,
            // Card paid through the provider: masked card, 3-D Secure, transaction reference.
            'card' => null === $cardAttempt ? null : self::attempt($cardAttempt),
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
