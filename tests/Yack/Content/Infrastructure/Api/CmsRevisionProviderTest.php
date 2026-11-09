<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsRevision;
use App\Entity\Content\CmsTemplate;
use App\Tests\Yack\Content\Application\InMemoryCmsRevisions;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsRevisionAuthor;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\CmsRevisionProvider;
use App\Yack\Content\Infrastructure\Api\CmsRevisionView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(CmsRevisionProvider::class)]
final class CmsRevisionProviderTest extends TestCase
{
    private InMemoryCmsRevisions $revisions;

    private CmsPage $page;

    private CmsTemplate $template;

    #[\Override]
    protected function setUp(): void
    {
        $this->revisions = new InMemoryCmsRevisions();
        $this->page = new CmsPage('accueil', 'Accueil');
        InMemoryCmsRevisions::identify($this->page, 1);
        $this->template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        InMemoryCmsRevisions::identify($this->template, 4);
        $author = new CmsRevisionAuthor('Sophie', 'Martin', 'Sophie Martin');
        foreach ([[$this->page, 'Accueil'], [$this->template, 'Fiche'], [$this->page, 'Accueil 2']] as [$target, $title]) {
            $target instanceof CmsPage ? $target->setTitle($title) : $target->setName($title);
            $this->revisions->add($target instanceof CmsPage ? CmsRevision::ofPage($target, $author) : CmsRevision::ofTemplate($target, $author));
            $this->revisions->flush();
        }
    }

    public function testTheRevisionsOfAPageAreListedNewestFirst(): void
    {
        $list = $this->provider()->provide(new GetCollection(), ['slug' => 'accueil']);

        $this->assertIsArray($list);
        $this->assertSame([3, 1], array_map(static fn (CmsRevisionView $view): int => $view->id, $list));
        $this->assertSame(['Accueil 2', 'Accueil'], array_map(static fn (CmsRevisionView $view): string => $view->title, $list));
        $this->assertSame('accueil', $list[0]->targetId);
        $this->assertSame('Sophie Martin', $list[0]->author['name'] ?? null);
        $this->assertNull($list[0]->content);
    }

    public function testTheRevisionsOfATemplateAreListedByItsId(): void
    {
        $list = $this->provider()->provide(new GetCollection(), ['id' => '4']);

        $this->assertIsArray($list);
        $this->assertSame([2], array_map(static fn (CmsRevisionView $view): int => $view->id, $list));
        $this->assertSame('template', $list[0]->target);
    }

    public function testAnUnknownTargetIsA404(): void
    {
        foreach ([['slug' => 'absente'], ['id' => '99'], ['id' => 'x'], []] as $variables) {
            try {
                $this->provider()->provide(new GetCollection(), $variables);
                $this->fail('NotFoundHttpException expected');
            } catch (NotFoundHttpException $exception) {
                $this->assertContains($exception->getMessage(), ['Page introuvable.', 'Template introuvable.']);
            }
        }
    }

    public function testOneRevisionComesWithItsContent(): void
    {
        $view = $this->provider()->provide(new Get(), ['id' => '1']);

        $this->assertInstanceOf(CmsRevisionView::class, $view);
        $this->assertSame('Accueil', $view->content['title'] ?? null);
        $this->assertNull($this->provider()->provide(new Get(), ['id' => '99']));
        $this->assertNull($this->provider()->provide(new Get(), ['id' => 'x']));
    }

    private function provider(): CmsRevisionProvider
    {
        $pages = $this->createStub(CmsPages::class);
        $pages->method('find')->willReturnCallback(fn (string $slug): ?CmsPage => 'accueil' === $slug ? $this->page : null);
        $templates = $this->createStub(CmsTemplates::class);
        $templates->method('find')->willReturnCallback(fn (int $id): ?CmsTemplate => 4 === $id ? $this->template : null);

        return new CmsRevisionProvider($this->revisions, $pages, $templates);
    }
}
