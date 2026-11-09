<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Application\ReplaceStyleEverywhere;
use App\Yack\Content\Application\StyleReplacementOutdated;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\StyleReplacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReplaceStyleEverywhere::class)]
final class ReplaceStyleEverywhereTest extends TestCase
{
    private InMemoryCmsRevisions $revisions;

    private InMemoryCmsContentTransaction $transaction;

    private CmsPage $offer;

    private CmsPage $services;

    private CmsPage $untouched;

    private CmsTemplate $template;

    #[\Override]
    protected function setUp(): void
    {
        $this->revisions = new InMemoryCmsRevisions();
        $this->transaction = new InMemoryCmsContentTransaction($this->revisions);
        $this->offer = self::page(1, 'offre', 'Offre', [
            ['type' => 'cta', 'props' => ['title' => 'A'], 'style' => ['fond' => '#ff7700', 'alignement' => 'centre']],
            ['type' => 'cta', 'props' => ['title' => 'B'], 'style' => ['texte' => '#ff7700']],
            ['type' => 'cta', 'props' => ['title' => 'C'], 'style' => ['fond' => '#ff7700']],
        ]);
        $this->services = self::page(2, 'nos-services', 'Nos services', [
            ['type' => 'faq', 'props' => ['title' => 'D'], 'style' => ['fond' => '#ff7700']],
        ]);
        $this->services->setStatus(CmsPageStatus::Draft);
        $this->untouched = self::page(3, 'le-groupe', 'Le groupe', [
            ['type' => 'cta', 'props' => ['title' => 'E'], 'style' => ['fond' => 'teal']],
        ]);
        $this->template = new CmsTemplate(CmsTemplateType::Product, 'PAC premium');
        InMemoryCmsRevisions::identify($this->template, 7);
        $this->template->setBlocks([
            ['type' => 'produit-achat', 'props' => []],
            ['type' => 'cta', 'props' => ['title' => 'F'], 'style' => ['fond' => '#ff7700']],
        ]);
    }

    public function testThePreviewCountsTheBlocksOfEveryPageAndTemplateAndWritesNothing(): void
    {
        $preview = $this->service()->preview(StyleReplacement::of('fond', '#ff7700', 'magenta'));

        $this->assertSame([
            ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => [1, 3]],
            ['target' => CmsRevisionTarget::Page, 'ref' => 'nos-services', 'label' => 'Nos services', 'blocks' => [1]],
            ['target' => CmsRevisionTarget::Template, 'ref' => '7', 'label' => 'PAC premium', 'blocks' => [2]],
        ], $preview->targets);
        $this->assertSame(4, $preview->blocks());
        $this->assertSame('#ff7700', $this->offer->getBlocks()[0]['style']['fond'] ?? null);
        $this->assertSame(0, $this->transaction->runs);
        $this->assertSame([], $this->revisions->pending);
    }

    public function testEveryBlockHoldingTheValueGetsTheCharteValueAndEachTargetARevisionOfTheAuthor(): void
    {
        $author = new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin');

        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $author, 4);

        $this->assertSame(4, $result->blocks());
        $this->assertSame(['fond' => 'magenta', 'alignement' => 'centre'], $this->offer->getBlocks()[0]['style'] ?? null);
        $this->assertSame(['texte' => '#ff7700'], $this->offer->getBlocks()[1]['style'] ?? null, 'other property, same value: unchanged');
        $this->assertSame(['fond' => 'magenta'], $this->offer->getBlocks()[2]['style'] ?? null);
        $this->assertSame(['fond' => 'magenta'], $this->services->getBlocks()[0]['style'] ?? null, 'drafts too');
        $this->assertSame(['fond' => 'magenta'], $this->template->getBlocks()[1]['style'] ?? null);
        $this->assertSame(['fond' => 'teal'], $this->untouched->getBlocks()[0]['style'] ?? null);
        $this->assertSame('A', $this->offer->getBlocks()[0]['props']['title'] ?? null);

        $this->assertSame(1, $this->transaction->commits, 'one transaction');
        $this->assertCount(3, $this->revisions->stored, 'one revision per target touched');
        foreach ([$this->offer, $this->services, $this->template] as $target) {
            $summaries = $this->revisions->summaries($target);
            $this->assertCount(1, $summaries);
            $this->assertSame('Sophie Martin', $summaries[0]->author?->name);
        }
        $this->assertSame([], $this->revisions->summaries($this->untouched));
        $this->assertSame($this->offer->getBlocks(), $this->revisions->stored[0]->getContent()['blocks'] ?? null, 'the revision keeps the new content');
    }

    public function testAnOutdatedPreviewReplacesNothing(): void
    {
        try {
            $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), null, 3);
            $this->fail('outdated');
        } catch (StyleReplacementOutdated $exception) {
            $this->assertSame(3, $exception->expected);
            $this->assertSame(4, $exception->actual);
        }

        $this->assertSame('#ff7700', $this->offer->getBlocks()[0]['style']['fond'] ?? null);
        $this->assertSame('#ff7700', $this->template->getBlocks()[1]['style']['fond'] ?? null);
        $this->assertSame([], $this->revisions->stored);
        $this->assertSame(1, $this->transaction->rollbacks);
        $this->assertSame(0, $this->transaction->commits);
    }

    public function testWithoutExpectedCountTheReplacementIsNotChecked(): void
    {
        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'plum'), null);

        $this->assertSame(4, $result->blocks());
        $this->assertNull($this->revisions->summaries($this->offer)[0]->author ?? null, 'no administrator: a revision without author');
        $this->assertSame('plum', $this->offer->getBlocks()[0]['style']['fond'] ?? null);
    }

    public function testAValueNoBlockHoldsChangesNothing(): void
    {
        $result = $this->service()(StyleReplacement::of('titre', '44px', 'l'), null, 0);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([], $this->revisions->stored);
        $this->assertSame(1, $this->transaction->commits);
    }

    private function service(): ReplaceStyleEverywhere
    {
        return new ReplaceStyleEverywhere(
            new InMemoryCmsPages([$this->offer, $this->services, $this->untouched]),
            new InMemoryCmsTemplates([$this->template]),
            new RecordCmsRevision($this->revisions),
            $this->transaction,
        );
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function page(int $id, string $slug, string $title, array $blocks): CmsPage
    {
        $page = new CmsPage($slug, $title);
        InMemoryCmsRevisions::identify($page, $id);
        $page->setStatus(CmsPageStatus::Published);
        $page->setBlocks($blocks);

        return $page;
    }
}
