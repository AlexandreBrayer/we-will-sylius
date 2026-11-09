<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Application;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Yack\Content\Application\CmsEditLocking;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Application\ReplaceStyleEverywhere;
use App\Yack\Content\Application\StyleReplacementOutdated;
use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsRevisionTarget;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Domain\StyleReplacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

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

    private InMemoryCmsEditLocks $locks;

    private MockClock $clock;

    private CmsEditLocking $locking;

    private AdminUser $sophie;

    private AdminUser $paul;

    #[\Override]
    protected function setUp(): void
    {
        $this->locks = new InMemoryCmsEditLocks();
        $this->clock = new MockClock('2026-11-09 14:05:00', 'UTC');
        $this->locking = new CmsEditLocking($this->locks, $this->clock);
        $this->sophie = self::admin(1, 'Sophie', 'Martin');
        $this->paul = self::admin(2, 'Paul', 'Durand');
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
        $preview = $this->service()->preview(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie);

        $this->assertSame([
            ['target' => CmsRevisionTarget::Page, 'ref' => 'offre', 'label' => 'Offre', 'blocks' => ['Bloc n°1', 'Bloc n°3']],
            ['target' => CmsRevisionTarget::Page, 'ref' => 'nos-services', 'label' => 'Nos services', 'blocks' => ['Bloc n°1']],
            ['target' => CmsRevisionTarget::Template, 'ref' => '7', 'label' => 'PAC premium', 'blocks' => ['Bloc n°2']],
        ], $preview->targets);
        $this->assertSame(4, $preview->blocks());
        $this->assertSame([], $preview->skipped);
        $this->assertSame('#ff7700', $this->offer->getBlocks()[0]['style']['fond'] ?? null);
        $this->assertSame(0, $this->transaction->runs);
        $this->assertSame([], $this->revisions->pending);
    }

    public function testEveryBlockHoldingTheValueGetsTheCharteValueAndEachTargetARevisionOfTheAuthor(): void
    {
        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, 4);

        $this->assertSame(4, $result->blocks());
        $this->assertSame([], $result->skipped);
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
        $this->assertSame(['page:1', 'page:2', 'template:7'], $this->locks->readsForWrite, 'the lock of each target read for the write');
    }

    public function testThePreviewAnnouncesAContentAnotherAdministratorEdits(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->paul);
        $this->clock->sleep(60);

        $preview = $this->service()->preview(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie);

        $this->assertSame([
            ['target' => CmsRevisionTarget::Page, 'ref' => 'nos-services', 'label' => 'Nos services', 'blocks' => ['Bloc n°1']],
            ['target' => CmsRevisionTarget::Template, 'ref' => '7', 'label' => 'PAC premium', 'blocks' => ['Bloc n°2']],
        ], $preview->targets);
        $this->assertCount(1, $preview->skipped);
        $this->assertSame(CmsRevisionTarget::Page, $preview->skipped[0]['target']);
        $this->assertSame('Offre', $preview->skipped[0]['label']);
        $this->assertSame(['Bloc n°1', 'Bloc n°3'], $preview->skipped[0]['blocks']);
        $this->assertSame('Paul Durand', $preview->skipped[0]['holder']);
        $this->assertSame('2026-11-09 14:05:00', $preview->skipped[0]['since']->format('Y-m-d H:i:s'));
        $this->assertSame([2, 2, 4], [$preview->blocks(), $preview->skippedBlocks(), $preview->matchedBlocks()]);
        $this->assertSame(0, $this->transaction->runs);
        $this->assertSame([], $this->locks->readsForWrite, 'a preview holds nothing');
    }

    public function testAContentAnotherAdministratorEditsIsLeftAsItIsAndListed(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->paul);
        $this->locking->apply(CmsEditLockAction::Acquire, $this->template, $this->paul);

        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, 4);

        $this->assertSame([['target' => CmsRevisionTarget::Page, 'ref' => 'nos-services', 'label' => 'Nos services', 'blocks' => ['Bloc n°1']]], $result->targets);
        $this->assertSame(['Offre', 'PAC premium'], array_column($result->skipped, 'label'));
        $this->assertSame(['Paul Durand', 'Paul Durand'], array_column($result->skipped, 'holder'));
        $this->assertSame(['fond' => '#ff7700', 'alignement' => 'centre'], $this->offer->getBlocks()[0]['style'] ?? null, 'not changed');
        $this->assertSame(['fond' => '#ff7700'], $this->template->getBlocks()[1]['style'] ?? null, 'not changed');
        $this->assertSame(['fond' => 'magenta'], $this->services->getBlocks()[0]['style'] ?? null);
        $this->assertSame([], $this->revisions->summaries($this->offer), 'no revision for a content left out');
        $this->assertSame([], $this->revisions->summaries($this->template));
        $this->assertCount(1, $this->revisions->summaries($this->services));
        $this->assertSame(1, $this->transaction->commits);
    }

    public function testWhenEveryContentIsLockedNothingIsWritten(): void
    {
        foreach ([$this->offer, $this->services, $this->template] as $target) {
            $this->locking->apply(CmsEditLockAction::Acquire, $target, $this->paul);
        }

        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, 4);

        $this->assertSame(0, $result->blocks());
        $this->assertSame(4, $result->skippedBlocks());
        $this->assertFalse($result->isEmpty());
        $this->assertSame([], $this->revisions->stored);
        $this->assertSame('#ff7700', $this->services->getBlocks()[0]['style']['fond'] ?? null);
    }

    public function testHisOwnLockAndASilentLockDoNotStopTheReplacement(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->sophie);
        $this->locking->apply(CmsEditLockAction::Acquire, $this->template, $this->paul);
        $this->clock->sleep(120);
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->sophie);

        $preview = $this->service()->preview(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie);
        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, 4);

        $this->assertSame([[], 4], [$preview->skipped, $preview->blocks()]);
        $this->assertSame([[], 4], [$result->skipped, $result->blocks()]);
        $this->assertSame(['fond' => 'magenta'], $this->template->getBlocks()[1]['style'] ?? null, 'lock of Paul silent for two minutes');
        $this->assertSame('magenta', $this->offer->getBlocks()[0]['style']['fond'] ?? null, 'her own lock');
    }

    public function testACommandIsNeverStoppedByALock(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->paul);

        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), null, 4);

        $this->assertSame([[], 4], [$result->skipped, $result->blocks()]);
        $this->assertSame('magenta', $this->offer->getBlocks()[0]['style']['fond'] ?? null);
    }

    public function testALockTakenAfterThePreviewLeavesTheContentOutAtTheConfirmation(): void
    {
        $preview = $this->service()->preview(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie);
        $this->locking->apply(CmsEditLockAction::Acquire, $this->services, $this->paul);

        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, $preview->matchedBlocks());

        $this->assertSame(['Nos services'], array_column($result->skipped, 'label'));
        $this->assertSame(3, $result->blocks());
        $this->assertSame('#ff7700', $this->services->getBlocks()[0]['style']['fond'] ?? null);
    }

    public function testTheConfirmationChecksTheBlocksHoldingTheValueLockedOnesIncluded(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->paul);
        $preview = $this->service()->preview(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie);
        $this->locking->apply(CmsEditLockAction::Release, $this->offer, $this->paul, $this->locks->find($this->offer)?->token);

        $result = $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, $preview->matchedBlocks());

        $this->assertSame([2, 4], [$preview->blocks(), $preview->matchedBlocks()]);
        $this->assertSame([[], 4], [$result->skipped, $result->blocks()], 'given back in between: replaced as well');
    }

    public function testTheBlocksOfALockedContentStillCountForTheConfirmation(): void
    {
        $this->locking->apply(CmsEditLockAction::Acquire, $this->offer, $this->paul);

        $this->expectException(StyleReplacementOutdated::class);
        $this->service()(StyleReplacement::of('fond', '#ff7700', 'magenta'), $this->sophie, 2);
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
            $this->locking,
        );
    }

    private static function admin(int $id, string $firstName, string $lastName): AdminUser
    {
        $admin = new AdminUser();
        (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($admin, $id);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);

        return $admin;
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
