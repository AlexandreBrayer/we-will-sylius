<?php

declare(strict_types=1);

namespace App\Yack\Payment\Domain\Card;

use App\Entity\Customer\Customer;
use App\Entity\Payment\SavedCard;

/**
 * Port: cards saved by the customers (provider token + masked card, never the number).
 */
interface SavedCards
{
    /** Saves the card, or refreshes the one already saved with the same brand, last digits and expiry. */
    public function save(Customer $customer, WalletCard $walletCard): SavedCard;

    /** @return list<SavedCard> newest first */
    public function allFor(Customer $customer): array;

    public function getFor(Customer $customer, int $id): ?SavedCard;

    public function remove(SavedCard $card): void;
}
