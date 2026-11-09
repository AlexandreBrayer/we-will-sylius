<?php

declare(strict_types=1);

namespace App\Yack\Payment\Infrastructure\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /shop/yack/orders/{tokenValue}/card-payment: a saved card (one-click) or none
 * (card typed on the hosted payment page).
 */
final class CardPaymentStartInput
{
    #[Assert\Positive]
    public ?int $savedCardId = null;
}
