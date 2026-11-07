<?php

declare(strict_types=1);

namespace App\Tests\Yack\Assistance\Domain;

use App\Yack\Assistance\Domain\ModelReference;
use App\Yack\Assistance\Domain\ReferenceBrand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReferenceBrand::class)]
final class ReferenceBrandTest extends TestCase
{
    /** @return \Iterator<string, array{string, ?string}> */
    public static function references(): \Iterator
    {
        yield 'mural MHI' => ['SRK25ZS-W', 'MHI'];
        yield 'groupe MHI corrigé' => ['5RK25ZS-W', 'MHI'];
        yield 'gainable MHI non découpable' => ['FDCW-VNX-A', 'MHI'];
        yield 'télécommande MHI' => ['RC-EX3D', 'MHI'];
        yield 'commande centralisée MHI' => ['SC-SL4', 'MHI'];
        yield 'multi MHI' => ['SCM40ZS-W', 'MHI'];
        yield 'ballon Yack' => ['YORABTM200', 'Yack'];
        yield 'plancher Yack' => ['EZH-ZP', 'Yack'];
        yield 'HECOZ Yack, pas HMU MHI' => ['HECOZ', 'Yack'];
        yield 'RHOSS' => ['RHOSS-AIREAU-R32', 'RHOSS'];
        yield 'inconnue' => ['ZZZ', null];
    }

    #[DataProvider('references')]
    public function testTheBrandIsDeducedFromThePrefix(string $input, ?string $brand): void
    {
        $reference = ModelReference::fromInput($input);
        \assert(null !== $reference);

        $this->assertSame($brand, ReferenceBrand::of($reference)?->name);
    }

    public function testADeducedBrandSaysSo(): void
    {
        $this->assertSame('deduite', ReferenceBrand::SOURCE);
    }
}
