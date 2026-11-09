<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Domain\Card;

use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\CardPaymentAttempt;
use App\Yack\Payment\Domain\Card\CardPaymentResult;
use App\Yack\Payment\Domain\Card\CardPaymentSession;
use App\Yack\Payment\Domain\Card\CardPaymentStatus;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\WalletCard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CardPaymentAttempt::class)]
final class CardPaymentAttemptTest extends TestCase
{
    public function testAOneClickAttemptStartsWithTheSavedCard(): void
    {
        $card = new MaskedCard(CardBrand::Visa, '4242', 12, 2030);
        $attempt = CardPaymentAttempt::start(new CardPaymentSession('s1', '/retour'), new WalletCard('tok', $card), new \DateTimeImmutable('2026-11-09 10:00'));

        $this->assertSame(
            ['s1', CardPaymentStatus::Pending, true, 'Visa •••• 4242', 'Paiement en cours'],
            [$attempt->sessionId, $attempt->status, $attempt->oneClick, $attempt->card?->label(), $attempt->message()],
        );
    }

    public function testAPendingResultLeavesTheAttemptAsItIs(): void
    {
        $attempt = CardPaymentAttempt::start(new CardPaymentSession('s1', '/page'), null, new \DateTimeImmutable('2026-11-09 10:00'));

        $this->assertSame($attempt, $attempt->conclude(CardPaymentResult::pending(), new \DateTimeImmutable('2026-11-09 10:05')));
    }

    public function testARefusalKeepsTheReasonAndTheTransaction(): void
    {
        $card = new MaskedCard(CardBrand::Visa, '9995', 12, 2030);
        $attempt = CardPaymentAttempt::start(new CardPaymentSession('s1', '/page'), null, new \DateTimeImmutable('2026-11-09 10:00'))
            ->conclude(new CardPaymentResult(CardPaymentStatus::Refused, 'SIM-1', $card, false, 'fonds insuffisants'), new \DateTimeImmutable('2026-11-09 10:05'));

        $this->assertSame(
            [CardPaymentStatus::Refused, 'SIM-1', 'Visa •••• 9995', 'Paiement refusé : fonds insuffisants', false],
            [$attempt->status, $attempt->transactionReference, $attempt->card?->label(), $attempt->message(), $attempt->cardSaved],
        );
    }

    public function testItIsStoredInThePaymentDetailsNextToTheOtherKeys(): void
    {
        $card = new MaskedCard(CardBrand::Mastercard, '4444', 1, 2031);
        $attempt = CardPaymentAttempt::start(new CardPaymentSession('s1', '/page'), null, new \DateTimeImmutable('2026-11-09T10:00:00+01:00'))
            ->conclude(new CardPaymentResult(CardPaymentStatus::Accepted, 'SIM-2', $card, true, null, 'tok'), new \DateTimeImmutable('2026-11-09T10:05:00+01:00'));

        $details = $attempt->intoDetails(['other' => 'kept']);
        $read = CardPaymentAttempt::fromDetails($details);

        $this->assertSame(
            ['kept', CardPaymentStatus::Accepted, 'SIM-2', '01/31', true, true, '2026-11-09T10:05:00+01:00'],
            [$details['other'], $read?->status, $read?->transactionReference, $read?->card?->expiry(), $read?->threeDSecure, $read?->cardSaved, $read?->concludedAt?->format(\DateTimeInterface::ATOM)],
        );
    }

    public function testAPaymentWithoutCardAttemptHasNone(): void
    {
        $this->assertSame([null, null], [CardPaymentAttempt::fromDetails([]), CardPaymentAttempt::fromDetails([CardPaymentAttempt::DETAILS_KEY => 'broken'])]);
    }
}
