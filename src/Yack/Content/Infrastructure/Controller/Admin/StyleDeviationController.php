<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Controller\Admin;

use App\Entity\User\AdminUser;
use App\Yack\Content\Application\ReplaceStyleEverywhere;
use App\Yack\Content\Application\StyleDeviationReport;
use App\Yack\Content\Application\StyleReplacementOutdated;
use App\Yack\Content\Application\StyleReplacementResult;
use App\Yack\Content\Domain\BlockStyle;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\InvalidStyleReplacement;
use App\Yack\Content\Domain\StyleReplacement;
use App\Yack\Content\Infrastructure\Security\StyleDeviationVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office: report of the style values outside the charte (every page and template, computed on
 * reading) and « Remplacer partout » (a free value replaced by a value of the charte, with a revision per
 * target). Administrators of level `libre` only (StyleDeviationVoter), checked on every request; the
 * replacement is a POST protected by a CSRF token. Secured by the Sylius admin firewall.
 * A page or template another administrator edits in the builder (edit lock) is announced on the preview,
 * left as it is by the replacement and listed afterwards (who edits it, since when).
 */
#[Route('/%sylius_admin.path_name%/yack/contenu/ecarts-de-charte')]
final class StyleDeviationController extends AbstractController
{
    public const CSRF_ID = 'yack_style_replace';

    public function __construct(
        private readonly StyleDeviationReport $report,
        private readonly ReplaceStyleEverywhere $replaceEverywhere,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_yack_admin_style_deviation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(StyleDeviationVoter::ATTRIBUTE);

        $all = $this->report->lines();
        $targets = StyleDeviationReport::targets($all);
        $target = $request->query->getString('cible');
        $target = isset($targets[$target]) ? $target : null;
        $property = self::property($request->query->getString('reglage'));
        $lines = StyleDeviationReport::filter($all, $target, $property);

        return $this->render('admin/yack/content/style_deviations.html.twig', [
            'lines' => $lines,
            'summary' => StyleDeviationReport::summary($lines),
            'total' => StyleDeviationReport::summary($all),
            'filtered' => null !== $target || null !== $property,
            'targets' => $targets,
            'properties' => self::properties(),
            'target' => $target,
            'property' => $property,
            'values' => StyleDeviationReport::values($all),
        ]);
    }

    #[Route('/remplacer', name: 'app_yack_admin_style_deviation_replace', methods: ['GET'])]
    public function confirm(Request $request): Response
    {
        $this->denyAccessUnlessGranted(StyleDeviationVoter::ATTRIBUTE);

        $property = $request->query->getString('reglage');

        try {
            $from = StyleReplacement::freeValue($property, $request->query->getString('valeur'));
        } catch (InvalidStyleReplacement $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_yack_admin_style_deviation_index');
        }

        $choices = StyleReplacement::charteValues($property);
        $asked = $request->query->getString('remplacement');
        $selected = in_array($asked, array_column($choices, 'key'), true) ? $asked : $choices[0]['key'];
        $replacement = StyleReplacement::of($property, $from, $selected);
        $preview = $this->replaceEverywhere->preview($replacement, $this->admin());

        return $this->render('admin/yack/content/style_deviations_replace.html.twig', [
            'replacement' => $replacement,
            'propertyLabel' => BlockStyle::label($property),
            'color' => BlockStyle::KIND_COLOR === BlockStyle::kind($property) ? $from : null,
            'preview' => $preview,
            'locked' => self::locked($preview, $this->clock->now()),
            'choices' => $choices,
            'isColor' => BlockStyle::KIND_COLOR === BlockStyle::kind($property),
            'csrfId' => self::CSRF_ID,
        ]);
    }

    #[Route('/remplacer', name: 'app_yack_admin_style_deviation_replace_confirm', methods: ['POST'])]
    public function replace(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(StyleDeviationVoter::ATTRIBUTE);

        $property = $request->request->getString('reglage');
        $value = $request->request->getString('valeur');
        $to = $request->request->getString('remplacement');
        [$backRoute, $backParameters] = self::back($property, $value, $to);
        $back = $this->redirectToRoute($backRoute, $backParameters, Response::HTTP_SEE_OTHER);

        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->request->getString('_token'))) {
            $this->addFlash('error', 'La demande n\'a pas pu être vérifiée (formulaire expiré ou incomplet) : rien n\'a été remplacé. Vérifiez puis confirmez à nouveau.');

            return $back;
        }

        try {
            $replacement = StyleReplacement::of($property, $value, $to);
            $expected = $request->request->getString('blocs');
            $result = ($this->replaceEverywhere)($replacement, $this->admin(), '' === $expected ? null : (int) $expected);
        } catch (InvalidStyleReplacement|StyleReplacementOutdated $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $back;
        }

        if ($result->isEmpty() || $result->blocks() > 0) {
            $this->addFlash($result->isEmpty() ? 'info' : 'success', self::done($replacement, $result));
        }
        if ([] !== $result->skipped) {
            $this->addFlash('warning', self::skipped($result, $this->clock->now()));
        }

        return $this->redirectToRoute('app_yack_admin_style_deviation_index', [], Response::HTTP_SEE_OTHER);
    }

    /** Message after a replacement: what changed, where, and the revisions created. */
    public static function done(StyleReplacement $replacement, StyleReplacementResult $result): string
    {
        if ($result->isEmpty()) {
            return sprintf('Aucun bloc ne porte plus %s sur « %s » : rien n\'a été remplacé.', $replacement->from, BlockStyle::label($replacement->property));
        }

        $where = array_filter([
            0 === $result->pages() ? null : sprintf('%d page%s', $result->pages(), $result->pages() > 1 ? 's' : ''),
            0 === $result->templates() ? null : sprintf('%d template%s', $result->templates(), $result->templates() > 1 ? 's' : ''),
        ]);

        return sprintf(
            '« %s » : %s remplacé par %s dans %d bloc%s (%s). Une révision à votre nom est créée pour chaque page ou template modifié.',
            BlockStyle::label($replacement->property),
            $replacement->from,
            $replacement->toLabel(),
            $result->blocks(),
            $result->blocks() > 1 ? 's' : '',
            implode(' et ', $where),
        );
    }

    /**
     * Message after a replacement that left out contents locked by another administrator: which ones, who
     * edits them and since when, and what to do.
     */
    public static function skipped(StyleReplacementResult $result, \DateTimeImmutable $now): string
    {
        $where = implode(' et ', array_filter([
            0 === $result->skippedPages() ? null : sprintf('%d page%s', $result->skippedPages(), $result->skippedPages() > 1 ? 's' : ''),
            0 === $result->skippedTemplates() ? null : sprintf('%d template%s', $result->skippedTemplates(), $result->skippedTemplates() > 1 ? 's' : ''),
        ]));

        return sprintf(
            '%s dans %s en cours de modification par un autre administrateur. %s Relancez « Remplacer partout » quand %s : la valeur reste dans le rapport tant qu\'elle n\'est pas remplacée.',
            0 === $result->blocks() ? 'Rien n\'a été remplacé : la valeur n\'est que' : 'Non remplacé',
            $where,
            implode(' ', self::locked($result, $now)),
            1 === \count($result->skipped) ? 'ce contenu est libre' : 'ces contenus sont libres',
        );
    }

    /**
     * One line per page or template left out: « Page « Accueil » (bloc n°2, section 3 › bloc 1) : Sophie Martin la modifie
     * dans le builder depuis 14 h 05. » The time is the time of the server, as everywhere in the back-office.
     *
     * @return list<string>
     */
    public static function locked(StyleReplacementResult $result, \DateTimeImmutable $now): array
    {
        return array_map(static function (array $target) use ($now): string {
            $page = CmsRevisionTarget::Page === $target['target'];

            return sprintf(
                '%s « %s » (%s) : %s %s modifie dans le builder depuis %s.',
                $page ? 'Page' : 'Template',
                $target['label'],
                mb_strtolower(implode(', ', $target['blocks'])),
                $target['holder'],
                $page ? 'la' : 'le',
                self::since($target['since'], $now),
            );
        }, $result->skipped);
    }

    /** « 14 h 05 » the same day, « 08/11/2026 à 14 h 05 » another day (time zone of $now). */
    public static function since(\DateTimeImmutable $since, \DateTimeImmutable $now): string
    {
        $since = $since->setTimezone($now->getTimezone());
        $time = $since->format('G \\h i');

        return $since->format('Y-m-d') === $now->format('Y-m-d') ? $time : sprintf('le %s à %s', $since->format('d/m/Y'), $time);
    }

    /**
     * Where a refused replacement goes back: its confirmation screen, or the report when the value to
     * replace is itself refused (the confirmation screen would refuse it again and repeat the message).
     *
     * @return array{string, array<string, string>} route and its parameters
     */
    public static function back(string $property, string $value, string $to): array
    {
        try {
            StyleReplacement::freeValue($property, $value);
        } catch (InvalidStyleReplacement) {
            return ['app_yack_admin_style_deviation_index', []];
        }

        return ['app_yack_admin_style_deviation_replace', ['reglage' => $property, 'valeur' => $value, 'remplacement' => $to]];
    }

    /**
     * Choices of the property filter: the properties that accept a free value.
     *
     * @return array<string, string> property => label
     */
    private static function properties(): array
    {
        $properties = [];
        foreach (StyleReplacement::properties() as $property) {
            $properties[$property] = BlockStyle::label($property);
        }

        return $properties;
    }

    /** Administrator who replaces: his own locks do not stop him (the admin firewall gives an AdminUser). */
    private function admin(): ?AdminUser
    {
        $user = $this->getUser();

        return $user instanceof AdminUser ? $user : null;
    }

    private static function property(string $asked): ?string
    {
        return in_array($asked, StyleReplacement::properties(), true) ? $asked : null;
    }
}
