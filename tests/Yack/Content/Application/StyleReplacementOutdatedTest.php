<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Yack\Content\Application\StyleReplacementOutdated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleReplacementOutdated::class)]
final class StyleReplacementOutdatedTest extends TestCase
{
    public function testTheMessageGivesBothCountsAndSaysNothingWasReplaced(): void
    {
        $exception = new StyleReplacementOutdated(2, 3);

        $this->assertSame(2, $exception->expected);
        $this->assertSame(3, $exception->actual);
        $this->assertSame('Le nombre de blocs concernés a changé depuis l\'aperçu (3 au lieu de 2) : rien n\'a été remplacé. Vérifiez puis confirmez à nouveau.', $exception->getMessage());
    }
}
