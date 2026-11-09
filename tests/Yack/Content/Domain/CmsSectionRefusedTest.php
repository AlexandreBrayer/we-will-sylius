<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsSectionRefused;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsSectionRefused::class)]
final class CmsSectionRefusedTest extends TestCase
{
    public function testTheRefusalNamesTheFieldOfTheRequest(): void
    {
        $name = CmsSectionRefused::name('Donnez un nom à la section.');
        $blocks = CmsSectionRefused::blocks('Une section contient au moins un bloc.');

        $this->assertSame(['name', 'Donnez un nom à la section.'], [$name->field, $name->getMessage()]);
        $this->assertSame(['blocks', 'Une section contient au moins un bloc.'], [$blocks->field, $blocks->getMessage()]);
    }
}
