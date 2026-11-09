<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\CmsTemplateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RecordCmsRevision::class)]
final class RecordCmsRevisionTest extends TestCase
{
    private InMemoryCmsRevisions $revisions;

    #[\Override]
    protected function setUp(): void
    {
        $this->revisions = new InMemoryCmsRevisions();
    }

    public function testAPageRevisionHoldsItsWholeContentAndItsAuthor(): void
    {
        $page = self::page(1);
        $page->setMetaTitle('Offre de printemps | Yack');
        $page->setStatus(CmsPageStatus::Published);
        $page->setBlocks([
            ['type' => 'cta', 'props' => ['title' => 'Vous êtes installateur ?', 'audience' => 'pro']],
            ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => '#ff7700', 'alignement' => 'centre']],
        ]);
        $page->setGrapesData(['pages' => [['id' => 'p1']]]);

        $revision = $this->record()($page, new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin'));

        $this->assertSame(CmsRevisionTarget::Page, $revision->target());
        $this->assertSame($page, $revision->getPage());
        $this->assertNull($revision->getTemplate());
        $this->assertSame('Offre', $revision->getTitle());
        $this->assertSame(CmsPageStatus::Published, $revision->getStatus());
        $this->assertSame(2, $revision->getBlockCount());
        $this->assertSame([
            'title' => 'Offre',
            'metaTitle' => 'Offre de printemps | Yack',
            'metaDescription' => null,
            'status' => 'published',
            'blocks' => $page->getBlocks(),
            'grapesData' => ['pages' => [['id' => 'p1']]],
        ], $revision->getContent());
        $this->assertSame(['fond' => '#ff7700', 'alignement' => 'centre'], $page->getBlocks()[1]['style'] ?? null, 'blocks keep their style');
        $this->assertSame('pro', $page->getBlocks()[0]['props']['audience'] ?? null, 'blocks keep their audience');
        $this->assertSame('Sophie Martin', $revision->author()?->name);
        $this->assertSame('Martin', $revision->author()->lastName);
        $this->assertLessThan(5, time() - $revision->getCreatedAt()->getTimestamp());
    }

    public function testATemplateRevisionHoldsWhatTheBuilderLoads(): void
    {
        $template = new CmsTemplate(CmsTemplateType::Taxon, 'Standard');
        InMemoryCmsRevisions::identify($template, 3);
        $template->setBlocks([['type' => 'categorie-produits', 'props' => ['perPage' => '9']]]);
        $template->setStatus(CmsPageStatus::Published);
        $template->setDefault(true);

        $revision = $this->record()($template, null);

        $this->assertSame(CmsRevisionTarget::Template, $revision->target());
        $this->assertSame($template, $revision->getTemplate());
        $this->assertNull($revision->getPage());
        $this->assertSame('Standard', $revision->getTitle());
        $this->assertSame(1, $revision->getBlockCount());
        $this->assertSame([
            'name' => 'Standard',
            'type' => 'taxon',
            'isDefault' => true,
            'status' => 'published',
            'blocks' => $template->getBlocks(),
            'grapesData' => null,
        ], $revision->getContent());
        $this->assertNull($revision->author(), 'fixtures and commands record without author');
    }

    public function testTheRevisionIsWrittenByTheFlushOfTheCaller(): void
    {
        $this->record()(self::page(1), null);

        $this->assertSame([], $this->revisions->stored);
        $this->assertCount(1, $this->revisions->pending);
    }

    public function testThe51stSaveRemovesTheOldestRevisionOfTheSameTargetOnly(): void
    {
        $page = self::page(1);
        $other = self::page(2);
        for ($i = 1; $i <= 3; ++$i) {
            $this->save($other, "Autre $i");
        }
        for ($i = 1; $i <= 50; ++$i) {
            $this->save($page, "Version $i");
        }
        $this->assertCount(50, $this->revisions->summaries($page));

        $this->save($page, 'Version 51');

        $titles = array_map(static fn ($summary): string => $summary->title, $this->revisions->summaries($page));
        $this->assertCount(RecordCmsRevision::KEEP, $titles);
        $this->assertSame('Version 51', $titles[0]);
        $this->assertSame('Version 2', $titles[49]);
        $this->assertNotContains('Version 1', $titles);
        $this->assertCount(3, $this->revisions->summaries($other));
    }

    public function testANewTargetHasNothingToRemove(): void
    {
        $this->record()(new CmsPage('offre', 'Offre'), null);

        $this->assertSame(0, $this->revisions->removeCalls);
        $this->assertCount(1, $this->revisions->pending);
    }

    private function save(CmsPage $page, string $title): void
    {
        $page->setTitle($title);
        $this->record()($page, null);
        $this->revisions->flush();
    }

    private function record(): RecordCmsRevision
    {
        return new RecordCmsRevision($this->revisions);
    }

    private static function page(int $id): CmsPage
    {
        $page = new CmsPage('offre-' . $id, 'Offre');
        InMemoryCmsRevisions::identify($page, $id);

        return $page;
    }
}
