<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Domain;

use App\Yack\Content\Domain\CmsMediaInUse;
use App\Yack\Content\Domain\CmsMediaUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CmsMediaInUse::class)]
#[CoversClass(CmsMediaUsage::class)]
final class CmsMediaInUseTest extends TestCase
{
    public function testTheMessageNamesTheOnlyContent(): void
    {
        $inUse = new CmsMediaInUse([new CmsMediaUsage(CmsMediaUsage::PAGE, 'accueil', 'Accueil')]);

        $this->assertSame('Cette image est utilisée par un contenu : page « Accueil ». Retirez-la de ces contenus avant de la supprimer.', $inUse->getMessage());
    }

    public function testTheMessageNamesEveryPageAndTemplate(): void
    {
        $usages = [
            new CmsMediaUsage(CmsMediaUsage::PAGE, 'accueil', 'Accueil'),
            new CmsMediaUsage(CmsMediaUsage::TEMPLATE, '3', 'Fiche standard'),
        ];
        $inUse = new CmsMediaInUse($usages);

        $this->assertSame('Cette image est utilisée par 2 contenus : page « Accueil », template « Fiche standard ». Retirez-la de ces contenus avant de la supprimer.', $inUse->getMessage());
        $this->assertSame($usages, $inUse->usages);
        $this->assertSame(['template', '3', 'Fiche standard'], [$usages[1]->kind, $usages[1]->id, $usages[1]->title]);
    }
}
