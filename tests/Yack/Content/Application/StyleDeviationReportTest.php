<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Application\StyleDeviationLine;
use App\Yack\Content\Application\StyleDeviationReport;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\CmsTemplateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StyleDeviationReport::class)]
final class StyleDeviationReportTest extends TestCase
{
    private InMemoryCmsRevisions $revisions;

    private CmsPage $demo;

    private CmsPage $charte;

    private CmsPage $broken;

    private CmsTemplate $template;

    #[\Override]
    protected function setUp(): void
    {
        $this->revisions = new InMemoryCmsRevisions();
        $this->demo = self::page(1, 'demo', 'Démo', [
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => '#ff7700', 'texte' => 'white']],
            ['type' => 'cta', 'props' => [], 'style' => ['hautEspace' => '120px', 'titre' => '44px']],
        ]);
        $this->charte = self::page(2, 'le-groupe', 'Le groupe', [
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => 'teal', 'hautEspace' => 'xl']],
            ['type' => 'faq', 'props' => []],
        ]);
        // Stored as is (invalid content is kept for the validation to report it): a value the builder cannot produce.
        $this->broken = self::page(3, 'ancienne', 'Ancienne', [
            ['type' => 'inconnu', 'props' => [], 'style' => ['fond' => 'red']],
        ]);
        $this->template = new CmsTemplate(CmsTemplateType::Taxon, 'Pièces');
        InMemoryCmsRevisions::identify($this->template, 9);
        $this->template->setBlocks([
            ['type' => 'categorie-produits', 'props' => []],
            ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#FF7700']],
        ]);
    }

    public function testEveryFreeValueOfEveryPageAndTemplateIsALine(): void
    {
        $lines = $this->report()->lines();

        $this->assertSame([
            ['page:demo', 1, 'texte-image', 'Texte + image', 'fond', 'Fond', '#ff7700', '#ff7700'],
            ['page:demo', 2, 'cta', 'Bandeau d\'appel à l\'action', 'titre', 'Taille du titre', '44px', null],
            ['page:demo', 2, 'cta', 'Bandeau d\'appel à l\'action', 'hautEspace', 'Espace au-dessus', '120px', null],
            ['page:ancienne', 1, 'inconnu', 'inconnu', 'fond', 'Fond', 'red', null],
            ['template:9', 2, 'cta', 'Bandeau d\'appel à l\'action', 'fond', 'Fond', '#ff7700', '#ff7700'],
        ], array_map(static fn (StyleDeviationLine $line): array => [$line->targetKey, $line->block, $line->blockType, $line->blockLabel, $line->property, $line->propertyLabel(), $line->value, $line->color()], $lines));

        $this->assertSame([CmsRevisionTarget::Page, 'demo', 'Démo', null, null], [$lines[0]->target, $lines[0]->targetRef, $lines[0]->targetLabel, $lines[0]->templateType, $lines[0]->templateId()]);
        $this->assertSame([CmsRevisionTarget::Template, '9', 'Pièces', 'Catégorie', 9], [$lines[4]->target, $lines[4]->targetRef, $lines[4]->targetLabel, $lines[4]->templateType, $lines[4]->templateId()]);
    }

    public function testAFreeValueInAContainerOrANestedBlockIsALineWithItsPath(): void
    {
        $this->demo->setBlocks([
            ['type' => 'faq', 'props' => []],
            ['type' => 'section', 'props' => [], 'style' => ['fond' => '#123456'], 'children' => [
                ['type' => 'colonnes', 'props' => [], 'style' => [], 'columns' => [[], [['type' => 'faq', 'props' => [], 'style' => ['basEspace' => '120px']]]]],
            ]],
        ]);

        $this->assertSame([
            ['page:demo', 2, 'Section 2', 'section', 'Section', 'fond', '#123456'],
            ['page:demo', 2, 'Section 2 › colonnes 1 › colonne 2 › bloc 1', 'faq', 'FAQ', 'basEspace', '120px'],
        ], array_map(
            static fn (StyleDeviationLine $line): array => [$line->targetKey, $line->block, $line->where, $line->blockType, $line->blockLabel, $line->property, $line->value],
            array_values(array_filter($this->report()->lines(), static fn (StyleDeviationLine $line): bool => 'page:demo' === $line->targetKey)),
        ));
    }

    public function testTheLastChangeComesFromTheNewestRevisionOfTheTarget(): void
    {
        $record = new RecordCmsRevision($this->revisions);
        $record($this->demo, new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin'));
        $this->revisions->flush();
        $newest = $record($this->demo, new CmsRevisionAuthor('Éditeur', 'Yack', 'Éditeur Yack'));
        $record($this->template, null);
        $this->revisions->flush();

        $lines = $this->report()->lines();

        $this->assertSame('Éditeur Yack', $lines[0]->lastAuthor?->name);
        $this->assertSame($newest->getCreatedAt(), $lines[0]->lastSavedAt);
        $this->assertTrue($lines[0]->hasRevision());
        $this->assertNull($lines[4]->lastAuthor, 'a revision without author (fixtures)');
        $this->assertTrue($lines[4]->hasRevision());
        $this->assertNull($lines[3]->lastAuthor, 'no revision');
        $this->assertNull($lines[3]->lastSavedAt);
        $this->assertFalse($lines[3]->hasRevision());
    }

    public function testTheLinesAreFilteredByTargetAndByProperty(): void
    {
        $lines = $this->report()->lines();

        $this->assertCount(3, StyleDeviationReport::filter($lines, 'page:demo', null));
        $this->assertCount(3, StyleDeviationReport::filter($lines, null, 'fond'));
        $this->assertCount(1, StyleDeviationReport::filter($lines, 'page:demo', 'fond'));
        $this->assertSame([], StyleDeviationReport::filter($lines, 'page:le-groupe', null));
        $this->assertSame($lines, StyleDeviationReport::filter($lines, null, null));
    }

    public function testTheTargetFilterOffersTheTargetsHoldingADeviation(): void
    {
        $this->assertSame([
            'page:demo' => 'Page : Démo',
            'page:ancienne' => 'Page : Ancienne',
            'template:9' => 'Template catégorie : Pièces',
        ], StyleDeviationReport::targets($this->report()->lines()));
    }

    public function testTheSummaryCountsDeviationsPagesAndTemplates(): void
    {
        $lines = $this->report()->lines();

        $this->assertSame('5 écarts sur 2 pages et 1 template', StyleDeviationReport::summary($lines));
        $this->assertSame('3 écarts sur 1 page', StyleDeviationReport::summary(StyleDeviationReport::filter($lines, 'page:demo', null)));
        $this->assertSame('1 écart sur 1 page', StyleDeviationReport::summary(StyleDeviationReport::filter($lines, 'page:demo', 'fond')));
        $this->assertSame('1 écart sur 1 template', StyleDeviationReport::summary(StyleDeviationReport::filter($lines, 'template:9', null)));
        $this->assertSame('Aucun écart', StyleDeviationReport::summary([]));
    }

    public function testDistinctValuesAreCountedAndOnlyWellFormedOnesCanBeReplaced(): void
    {
        $this->assertSame([
            ['property' => 'fond', 'propertyLabel' => 'Fond', 'value' => '#ff7700', 'color' => '#ff7700', 'blocks' => 2, 'replaceable' => true],
            ['property' => 'titre', 'propertyLabel' => 'Taille du titre', 'value' => '44px', 'color' => null, 'blocks' => 1, 'replaceable' => true],
            ['property' => 'hautEspace', 'propertyLabel' => 'Espace au-dessus', 'value' => '120px', 'color' => null, 'blocks' => 1, 'replaceable' => true],
            ['property' => 'fond', 'propertyLabel' => 'Fond', 'value' => 'red', 'color' => null, 'blocks' => 1, 'replaceable' => false],
        ], StyleDeviationReport::values($this->report()->lines()));
    }

    public function testAContentWithinTheCharteHasNoLine(): void
    {
        $report = new StyleDeviationReport(new InMemoryCmsPages([$this->charte]), new InMemoryCmsTemplates(), $this->revisions);

        $this->assertSame([], $report->lines());
    }

    public function testBlocksStoredAsAJsonObjectAreReadInOrder(): void
    {
        // Only an SQL write can store the blocks under keys: the report reads them in order instead of failing.
        $stored = self::page(4, 'objet', 'Objet', [
            'a' => ['type' => 'faq', 'props' => [], 'style' => ['fond' => 'teal']],
            'b' => ['type' => 'cta', 'props' => [], 'style' => ['fond' => '#123456']],
        ]);
        $this->assertSame(['a', 'b'], array_keys($stored->getBlocks()), 'stored as is');

        $report = new StyleDeviationReport(new InMemoryCmsPages([$stored]), new InMemoryCmsTemplates(), $this->revisions);

        $this->assertSame(
            [['page:objet', 2, 'cta', 'fond', '#123456']],
            array_map(static fn (StyleDeviationLine $line): array => [$line->targetKey, $line->block, $line->blockType, $line->property, $line->value], $report->lines()),
        );
    }

    private function report(): StyleDeviationReport
    {
        return new StyleDeviationReport(
            new InMemoryCmsPages([$this->demo, $this->charte, $this->broken]),
            new InMemoryCmsTemplates([$this->template]),
            $this->revisions,
        );
    }

    /**
     * @param array<array<string, mixed>> $blocks
     */
    private static function page(int $id, string $slug, string $title, array $blocks): CmsPage
    {
        $page = new CmsPage($slug, $title);
        InMemoryCmsRevisions::identify($page, $id);
        $page->setBlocks($blocks);

        return $page;
    }
}
