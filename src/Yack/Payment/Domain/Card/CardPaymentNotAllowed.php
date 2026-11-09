<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

/**
 * The order cannot be paid by card now (already paid, cancelled, other payment method…).
 * The message is shown to the customer.
 */
final class CardPaymentNotAllowed extends \DomainException
{
    public static function orderNotAwaitingPayment(): self
    {
        return new self('Cette commande n\'attend pas de paiement.');
    }

    public static function notACardPayment(): self
    {
        return new self('Le mode de paiement de cette commande n\'est pas la carte bancaire.');
    }

    public static function unknownSavedCard(): self
    {
        return new self('Cette carte enregistrée n\'existe plus.');
    }

    public static function expiredSavedCard(): self
    {
        return new self('Cette carte enregistrée a expiré : payez avec une autre carte.');
    }

    public static function noSessionInProgress(): self
    {
        return new self('Aucun paiement par carte n\'est en cours pour cette commande.');
    }
}
