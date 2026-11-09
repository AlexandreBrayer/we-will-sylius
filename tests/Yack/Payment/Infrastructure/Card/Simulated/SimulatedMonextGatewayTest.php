<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Card\Simulated;

use App\Yack\Payment\Domain\Card\CardBrand;
use App\Yack\Payment\Domain\Card\CardPaymentRequest;
use App\Yack\Payment\Domain\Card\CardPaymentStatus;
use App\Yack\Payment\Domain\Card\MaskedCard;
use App\Yack\Payment\Domain\Card\WalletCard;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedIds;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedMonextGateway;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedPaymentPage;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedScenario;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedSession;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedSessions;
use App\Yack\Payment\Infrastructure\Card\Simulated\SimulatedStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(SimulatedMonextGateway::class)]
#[CoversClass(SimulatedPaymentPage::class)]
#[CoversClass(SimulatedSession::class)]
#[CoversClass(SimulatedSessions::class)]
#[CoversClass(SimulatedScenario::class)]
final class SimulatedMonextGatewayTest extends TestCase
{
    private const RETURN_URL = 'https://yack.test/commande/retour-paiement/tok';

    private SimulatedMonextGateway $gateway;
    private SimulatedPaymentPage $page;

    #[\Override]
    protected function setUp(): void
    {
        $sessions = new SimulatedSessions(new ArrayAdapter(storeSerialized: true));
        $this->gateway = new SimulatedMonextGateway($sessions, new SimulatedIds());
        $this->page = new SimulatedPaymentPage($sessions, new SimulatedIds());
    }

    public function testANewCardIsTypedOnTheHostedPage(): void
    {
        $session = $this->gateway->startSession(self::request());

        $this->assertSame(
            ['/paiement-securise/' . $session->id, CardPaymentStatus::Pending, SimulatedStep::Card],
            [$session->redirectUrl, $this->gateway->result($session->id)->status, $this->page->find($session->id)?->step()],
        );
    }

    /** @param list<string> $challengeCodes */
    #[DataProvider('cardScenarios')]
    public function testTheTestCardChoosesTheOutcome(string $last4, array $challengeCodes, CardPaymentStatus $status, bool $threeDSecure, ?string $reason): void
    {
        $id = $this->gateway->startSession(self::request())->id;
        $page = $this->page->find($id) ?? self::fail('Unknown session.');
        $this->page->submitCard($page, new MaskedCard(CardBrand::Visa, $last4, 12, 2030), false);
        foreach ($challengeCodes as $code) {
            $this->page->answerChallenge($this->page->find($id) ?? self::fail('Unknown session.'), $code);
        }
        $result = $this->gateway->result($id);

        $this->assertSame(
            [$status, $threeDSecure, $reason, CardPaymentStatus::Pending !== $status],
            [$result->status, $result->threeDSecure, $result->refusalReason, null !== $result->transactionReference],
        );
    }

    /** @return \Iterator<string, array{string, list<string>, CardPaymentStatus, bool, ?string}> */
    public static function cardScenarios(): \Iterator
    {
        yield 'accepted' => ['4242', [], CardPaymentStatus::Accepted, false, null];
        yield '3-D Secure, right code' => ['3220', ['123456'], CardPaymentStatus::Accepted, true, null];
        yield '3-D Secure, wrong code' => ['3220', ['000000'], CardPaymentStatus::Refused, true, 'authentification 3-D Secure échouée (code incorrect)'];
        yield '3-D Secure, not answered' => ['3220', [], CardPaymentStatus::Pending, false, null];
        yield 'insufficient funds' => ['9995', [], CardPaymentStatus::Refused, false, 'fonds insuffisants'];
        yield 'declined' => ['0002', [], CardPaymentStatus::Refused, false, 'carte refusée par la banque'];
        yield 'unknown card' => ['1234', [], CardPaymentStatus::Refused, false, 'carte inconnue de l\'environnement de démonstration'];
    }

    public function testTheCustomerCanCancelOnTheHostedPage(): void
    {
        $id = $this->gateway->startSession(self::request())->id;
        $this->page->cancel($this->page->find($id) ?? self::fail('Unknown session.'));

        $this->assertSame(CardPaymentStatus::Cancelled, $this->gateway->result($id)->status);
    }

    public function testAFinishedPaymentCannotBeCancelled(): void
    {
        $id = $this->gateway->startSession(self::request())->id;
        $this->page->submitCard($this->page->find($id) ?? self::fail('Unknown session.'), new MaskedCard(CardBrand::Visa, '4242', 12, 2030), false);

        $this->expectException(\DomainException::class);

        $this->page->cancel($this->page->find($id) ?? self::fail('Unknown session.'));
    }

    public function testACardSavedOnTheHostedPageGetsAToken(): void
    {
        $id = $this->gateway->startSession(self::request())->id;
        $this->page->submitCard($this->page->find($id) ?? self::fail('Unknown session.'), new MaskedCard(CardBrand::Mastercard, '4444', 12, 2030), true);
        $walletCard = $this->gateway->result($id)->walletCard();

        $this->assertSame(['Mastercard •••• 4444', true], [$walletCard?->card->label(), str_starts_with((string) $walletCard?->token, 'simtok_')]);
    }

    public function testASavedCardPaysInOneClickAndGoesBackToYack(): void
    {
        $session = $this->gateway->startSession(self::request(new WalletCard('SIMTOK-1', new MaskedCard(CardBrand::Visa, '4242', 12, 2030))));
        $result = $this->gateway->result($session->id);

        $this->assertSame([self::RETURN_URL, CardPaymentStatus::Accepted, null], [$session->redirectUrl, $result->status, $result->walletCard()]);
    }

    public function testASavedCardAskingFor3DSecureGoesToTheChallenge(): void
    {
        $session = $this->gateway->startSession(self::request(new WalletCard('SIMTOK-1', new MaskedCard(CardBrand::Visa, '3220', 12, 2030))));
        $page = $this->page->find($session->id);

        $this->assertSame(
            ['/paiement-securise/' . $session->id, SimulatedStep::Challenge, true],
            [$session->redirectUrl, $page?->step(), $page?->isOneClick()],
        );
    }

    public function testAnUnknownSessionCountsAsCancelled(): void
    {
        $this->assertSame(
            [CardPaymentStatus::Cancelled, null],
            [$this->gateway->result('0123456789abcdef0123456789abcdef')->status, $this->page->find('../not-an-id')],
        );
    }

    private static function request(?WalletCard $walletCard = null): CardPaymentRequest
    {
        return new CardPaymentRequest('000000042', 'YACK-000000042-7', 37_433, 'EUR', 'ecom@yack.test', self::RETURN_URL, $walletCard);
    }
}
