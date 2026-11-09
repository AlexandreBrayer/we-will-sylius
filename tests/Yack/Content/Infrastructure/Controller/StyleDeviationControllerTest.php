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

    public function testWhenNoBlockHoldsTheValueAnyMoreNothingIsSaidToBeReplaced(): void
    {
        $this->assertSame(
            'Aucun bloc ne porte plus #ff7700 sur « Fond » : rien n\'a été remplacé.',
            StyleDeviationController::done(StyleReplacement::of('fond', '#ff7700', 'magenta'), new StyleReplacementResult([])),
        );
    }
}
