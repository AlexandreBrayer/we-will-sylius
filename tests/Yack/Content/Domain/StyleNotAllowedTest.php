<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\StyleDeviation;
use App\Yack\Content\Domain\StyleNotAllowed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleNotAllowed::class)]
final class StyleNotAllowedTest extends TestCase
{
    public function testTellsTheBlockTheValueAndWhyInFrench(): void
    {
        $deviation = new StyleDeviation(2, 'fond', '#ff7700');
        $exception = new StyleNotAllowed($deviation);

        $this->assertSame('Bloc n°2 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.', $exception->getMessage());
        $this->assertSame($deviation, $exception->deviation);
    }

    public function testANestedBlockIsNamedByItsPath(): void
    {
        $exception = new StyleNotAllowed(new StyleDeviation(1, 'titre', '50px', 'Section 1 › colonnes 1 › colonne 2 › bloc 1', 'faq'));

        $this->assertSame('Section 1 › colonnes 1 › colonne 2 › bloc 1 : la taille de titre 50px est hors charte. Votre compte ne permet pas de sortir de la charte.', $exception->getMessage());
    }
}
