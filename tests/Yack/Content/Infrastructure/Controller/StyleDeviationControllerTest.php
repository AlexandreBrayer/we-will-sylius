<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Controller;

use App\Yack\Content\Application\StyleReplacementResult;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\StyleReplacement;
use App\Yack\Content\Infrastructure\Controller\Admin\StyleDeviationController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The requests (access, filters, CSRF, replacement) go through the real HTTP kernel in the check of the
 * ticket; here, the message shown after a replacement.
 *
 * @internal
 */
#[CoversClass(StyleDeviationController::class)]
final class StyleDeviationControllerTest extends TestCase
{
    public function testTheMessageSaysWhatChangedWhereAndThatRevisionsWereCreated(): void
    {
        $this->assertSame(
            '« Fond » : #ff7700 remplacé par Magenta (#a33c87) dans 3 blocs (2 pages et 1 template). Une révision à votre nom est créée pour chaque page ou template modifié.',
            StyleDeviationController::done(StyleReplacement::of('fond', '#ff7700', 'magenta'), new StyleReplacementResult([
                ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => [1]],
                ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => [2]],
                ['target' => CmsRevisionTarget::Template, 'ref' => '4', 'label' => 'Standard', 'blocks' => [3]],
            ])),
        );
        $this->assertSame(
            '« Espace au-dessus » : 120px remplacé par Très grand (80px) dans 1 bloc (1 page). Une révision à votre nom est créée pour chaque page ou template modifié.',
            StyleDeviationController::done(StyleReplacement::of('hautEspace', '120px', 'xl'), new StyleReplacementResult([
                ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => [2]],
            ])),
        );
    }

    public function testARefusedReplacementGoesBackToItsConfirmationUnlessTheValueToReplaceIsRefused(): void
    {
        $this->assertSame(
            ['app_yack_admin_style_deviation_replace', ['reglage' => 'fond', 'valeur' => '#FF7700', 'remplacement' => 'xl']],
            StyleDeviationController::back('fond', '#FF7700', 'xl'),
        );
        $this->assertSame(['app_yack_admin_style_deviation_index', []], StyleDeviationController::back('fond', 'magenta', 'teal'));
        $this->assertSame(['app_yack_admin_style_deviation_index', []], StyleDeviationController::back('fond', 'red', 'magenta'));
        $this->assertSame(['app_yack_admin_style_deviation_index', []], StyleDeviationController::back('alignement', 'centre', 'gauche'));
    }

    public function testTheContentsLeftOutAreListedWithWhoEditsThemAndSinceWhen(): void
    {
        $now = new \DateTimeImmutable('2026-11-09 16:30:00');
        $result = new StyleReplacementResult([
            ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => [2]],
        ], [
            ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo : écarts de charte', 'blocks' => [1], 'holder' => 'Sophie Martin', 'since' => new \DateTimeImmutable('2026-11-09 14:05:00')],
            ['target' => CmsRevisionTarget::Template, 'ref' => '4', 'label' => 'PAC premium', 'blocks' => [2, 5], 'holder' => 'Paul Durand', 'since' => new \DateTimeImmutable('2026-11-08 09:30:00')],
        ]);

        $this->assertSame([
            'Page « Démo : écarts de charte » (bloc n°1) : Sophie Martin la modifie dans le builder depuis 14 h 05.',
            'Template « PAC premium » (blocs n°2, n°5) : Paul Durand le modifie dans le builder depuis le 08/11/2026 à 9 h 30.',
        ], StyleDeviationController::locked($result, $now));
        $this->assertSame(
            'Non remplacé dans 1 page et 1 template en cours de modification par un autre administrateur. '
            . 'Page « Démo : écarts de charte » (bloc n°1) : Sophie Martin la modifie dans le builder depuis 14 h 05. '
            . 'Template « PAC premium » (blocs n°2, n°5) : Paul Durand le modifie dans le builder depuis le 08/11/2026 à 9 h 30. '
            . 'Relancez « Remplacer partout » quand ces contenus sont libres : la valeur reste dans le rapport tant qu\'elle n\'est pas remplacée.',
            StyleDeviationController::skipped($result, $now),
        );
    }

    public function testWhenEveryContentIsLockedTheMessageSaysNothingWasReplaced(): void
    {
        $result = new StyleReplacementResult([], [
            ['target' => CmsRevisionTarget::Page, 'ref' => 'demo', 'label' => 'Démo', 'blocks' => [1], 'holder' => 'Sophie Martin', 'since' => new \DateTimeImmutable('2026-11-09 14:05:00')],
        ]);

        $this->assertSame(
            'Rien n\'a été remplacé : la valeur n\'est que dans 1 page en cours de modification par un autre administrateur. '
            . 'Page « Démo » (bloc n°1) : Sophie Martin la modifie dans le builder depuis 14 h 05. '
            . 'Relancez « Remplacer partout » quand ce contenu est libre : la valeur reste dans le rapport tant qu\'elle n\'est pas remplacée.',
            StyleDeviationController::skipped($result, new \DateTimeImmutable('2026-11-09 14:06:00')),
        );
    }

    public function testTheTimeIsReadInTheTimeZoneOfNow(): void
    {
        $now = new \DateTimeImmutable('2026-11-09 08:00:00', new \DateTimeZone('America/Martinique'));

        $this->assertSame('7 h 05', StyleDeviationController::since(new \DateTimeImmutable('2026-11-09 11:05:00', new \DateTimeZone('UTC')), $now));
        $this->assertSame('le 08/11/2026 à 23 h 50', StyleDeviationController::since(new \DateTimeImmutable('2026-11-09 03:50:00', new \DateTimeZone('UTC')), $now));
    }

    public function testWhenNoBlockHoldsTheValueAnyMoreNothingIsSaidToBeReplaced(): void
    {
        $this->assertSame(
            'Aucun bloc ne porte plus #ff7700 sur « Fond » : rien n\'a été remplacé.',
            StyleDeviationController::done(StyleReplacement::of('fond', '#ff7700', 'magenta'), new StyleReplacementResult([])),
        );
    }
}
