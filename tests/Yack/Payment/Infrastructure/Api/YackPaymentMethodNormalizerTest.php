<?php

declare(strict_types=1);

namespace App\Tests\Yack\Payment\Infrastructure\Api;

use App\Yack\Payment\Domain\PaymentMethodEscompteRates;
use App\Yack\Payment\Domain\PaymentTerms;
use App\Yack\Payment\Infrastructure\Api\YackPaymentMethodNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\SectionResolver\AdminSection;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Needs Sylius (vendor/) : PHPUnit seulement.
 *
 * @internal
 */
#[CoversClass(YackPaymentMethodNormalizer::class)]
final class YackPaymentMethodNormalizerTest extends TestCase
{
    /** @return \Iterator<string, array{string, float|null, string|null, int|null}> */
    public static function methods(): \Iterator
    {
        yield 'virement à échéance' => ['virement_echeance', 0.0, null, 30];
        yield 'virement sous 15 jours' => ['virement_15j', 2.0, 'Escompte 2 %', 15];
        yield 'carte' => ['carte_bancaire', 2.0, 'Escompte 2 %', null];
        yield 'moyen sans réglage' => ['cheque', null, null, null];
    }

    #[DataProvider('methods')]
    public function testAShopPaymentMethodCarriesItsEscompteAndItsDays(string $code, ?float $rate, ?string $label, ?int $days): void
    {
        $normalized = $this->normalizer(new ShopApiSection())->normalize($this->method($code), 'jsonld');

        $this->assertSame(['code' => $code, 'escompteRate' => $rate, 'escompteLabel' => $label, 'dueDays' => $days], $normalized);
    }

    public function testOnlyShopApiPaymentMethodsAreEnriched(): void
    {
        $this->assertTrue($this->normalizer(new ShopApiSection())->supportsNormalization($this->method('virement_15j')));
        $this->assertFalse($this->normalizer(new AdminSection())->supportsNormalization($this->method('virement_15j')));
        $this->assertFalse($this->normalizer(new ShopApiSection())->supportsNormalization(new \stdClass()));
    }

    private function normalizer(SectionInterface $section): YackPaymentMethodNormalizer
    {
        $sections = $this->createMock(SectionProviderInterface::class);
        $sections->method('getSection')->willReturn($section);
        $inner = $this->createMock(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static fn (PaymentMethodInterface $method): array => ['code' => $method->getCode()]);

        $normalizer = new YackPaymentMethodNormalizer(
            $sections,
            new PaymentMethodEscompteRates(['virement_15j' => 2, 'carte_bancaire' => 2, 'virement_echeance' => 0]),
            new PaymentTerms(
                ['holder' => 'YACK SAS', 'bank' => 'Banque Fictive', 'iban' => 'FR76 0000', 'bic' => 'BFSUFRPPXXX'],
                ['virement_15j' => ['kind' => 'virement', 'due_days' => 15], 'virement_echeance' => ['kind' => 'virement', 'due_days' => 30], 'carte_bancaire' => ['kind' => 'carte']],
            ),
        );
        $normalizer->setNormalizer($inner);

        return $normalizer;
    }

    private function method(string $code): PaymentMethodInterface
    {
        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getCode')->willReturn($code);

        return $method;
    }
}
