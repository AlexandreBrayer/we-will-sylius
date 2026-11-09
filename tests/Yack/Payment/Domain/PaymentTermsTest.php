<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Domain;

use App\Yack\Payment\Domain\PaymentKind;
use App\Yack\Payment\Domain\PaymentTerms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(PaymentTerms::class)]
final class PaymentTermsTest extends TestCase
{
    private const BANK = ['holder' => 'YACK SAS (compte de démonstration)', 'bank' => 'Banque Fictive', 'iban' => 'FR76 0000', 'bic' => 'BFSUFRPPXXX'];

    public function testATransferSaysItsDaysAndACardNone(): void
    {
        $terms = new PaymentTerms(self::BANK, [
            'virement_15j' => ['kind' => 'virement', 'due_days' => 15],
            'virement_echeance' => ['kind' => 'virement', 'due_days' => 30],
            'carte_bancaire' => ['kind' => 'carte'],
        ]);

        $this->assertSame(15, $terms->dueDays('virement_15j'));
        $this->assertSame(30, $terms->dueDays('virement_echeance'));
        $this->assertNull($terms->dueDays('carte_bancaire'));
        $this->assertNull($terms->dueDays('inconnu'));
    }

    public function testTheDemoConfigurationGivesThirtyDaysToTheTransferOnDueDate(): void
    {
        $parameters = Yaml::parseFile(\dirname(__DIR__, 4).'/config/packages/yack.yaml')['parameters'];
        $terms = new PaymentTerms($parameters['app.yack.payment_terms.bank_account'], $parameters['app.yack.payment_terms.methods']);

        $this->assertSame(30, $terms->dueDays('virement_echeance'));
        $this->assertSame(15, $terms->dueDays('virement_15j'));
        $this->assertNull($terms->dueDays('carte_bancaire'));
    }

    public function testTheDueDateIsTheOrderDatePlusTheDays(): void
    {
        $terms = new PaymentTerms(self::BANK, ['virement_echeance' => ['kind' => 'virement', 'due_days' => 30], 'carte_bancaire' => ['kind' => 'carte']]);

        $instruction = $terms->instructionFor('virement_echeance', '000000180', new \DateTimeImmutable('2026-11-09 16:45'));
        $this->assertNotNull($instruction);
        $this->assertSame(PaymentKind::BankTransfer, $instruction->kind);
        $this->assertSame('2026-12-09', $instruction->dueDate?->format('Y-m-d'));
        $this->assertSame('YACK-000000180', $instruction->reference);
        $this->assertSame(PaymentKind::Card, $terms->instructionFor('carte_bancaire', '1', new \DateTimeImmutable())?->kind);
        $this->assertTrue($terms->isCard('carte_bancaire'));
        $this->assertNull($terms->instructionFor('inconnu', '1', new \DateTimeImmutable()));
    }

    public function testATransferWithoutDaysIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PaymentTerms(self::BANK, ['virement' => ['kind' => 'virement']]);
    }
}
