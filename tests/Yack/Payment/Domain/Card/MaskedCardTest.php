<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Domain\Card;

use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\MaskedCard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MaskedCard::class)]
final class MaskedCardTest extends TestCase
{
    public function testItShowsTheBrandTheLastDigitsAndTheExpiry(): void
    {
        $card = new MaskedCard(CardBrand::Visa, '4242', 3, 2028);

        $this->assertSame(['Visa •••• 4242', '03/28'], [$card->label(), $card->expiry()]);
    }

    public function testItGoesThroughTheDetailsOfASyliusPayment(): void
    {
        $card = new MaskedCard(CardBrand::Mastercard, '4444', 12, 2030);

        $this->assertSame(
            ['brand' => 'mastercard', 'last4' => '4444', 'expiryMonth' => 12, 'expiryYear' => 2030],
            MaskedCard::fromArray($card->toArray())->toArray(),
        );
    }

    public function testItIsValidUntilTheEndOfItsExpiryMonth(): void
    {
        $card = new MaskedCard(CardBrand::Visa, '4242', 11, 2026);

        $this->assertSame(
            [false, true],
            [$card->isExpiredAt(new \DateTimeImmutable('2026-11-30 23:59:59')), $card->isExpiredAt(new \DateTimeImmutable('2026-12-01 00:00:00'))],
        );
    }

    #[DataProvider('notMasked')]
    public function testItKeepsOnlyFourDigits(string $last4, int $month): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MaskedCard(CardBrand::Visa, $last4, $month, 2030);
    }

    /** @return \Iterator<string, array{string, int}> */
    public static function notMasked(): \Iterator
    {
        yield 'full number' => ['4242424242424242', 12];
        yield 'letters' => ['42a2', 12];
        yield 'month 13' => ['4242', 13];
    }
}
