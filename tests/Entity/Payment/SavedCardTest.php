<?php

declare(strict_types=1);

namespace App\Tests\Entity\Payment;

use App\Entity\Customer\Customer;
use App\Entity\Payment\SavedCard;
use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\WalletCard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SavedCard::class)]
final class SavedCardTest extends TestCase
{
    public function testItKeepsTheMaskedCardAndTheProviderToken(): void
    {
        $saved = new SavedCard(new Customer(), new WalletCard('simtok_1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030)), new \DateTimeImmutable('2026-11-09 10:00'));

        $this->assertSame(
            ['Visa •••• 4242', '12/30', 'simtok_1', '2026-11-09 10:00'],
            [$saved->card()->label(), $saved->card()->expiry(), $saved->walletCard()->token, $saved->getCreatedAt()->format('Y-m-d H:i')],
        );
    }

    #[DataProvider('cards')]
    public function testTheSameCardHasTheSameBrandDigitsAndExpiry(MaskedCard $card, bool $same): void
    {
        $saved = new SavedCard(new Customer(), new WalletCard('simtok_1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030)));

        $this->assertSame($same, $saved->isSameCardAs($card));
    }

    /** @return \Iterator<string, array{MaskedCard, bool}> */
    public static function cards(): \Iterator
    {
        yield 'same card' => [new MaskedCard(CardBrand::Visa, '4242', 12, 2030), true];
        yield 'other brand' => [new MaskedCard(CardBrand::Mastercard, '4242', 12, 2030), false];
        yield 'other digits' => [new MaskedCard(CardBrand::Visa, '3220', 12, 2030), false];
        yield 'renewed card' => [new MaskedCard(CardBrand::Visa, '4242', 12, 2031), false];
    }

    public function testSavingTheCardAgainTakesTheNewToken(): void
    {
        $saved = new SavedCard(new Customer(), new WalletCard('simtok_1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030)));
        $saved->renew(new WalletCard('simtok_2', new MaskedCard(CardBrand::Visa, '4242', 12, 2030)));

        $this->assertSame(['simtok_2', 'Visa •••• 4242'], [$saved->walletCard()->token, $saved->card()->label()]);
    }
}
