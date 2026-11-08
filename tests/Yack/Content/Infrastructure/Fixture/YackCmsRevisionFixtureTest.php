<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Fixture;

use App\Entity\Content\CmsPage;
use App\Entity\Content\CmsTemplate;
use App\Tests\Yack\Content\Application\InMemoryCmsRevisions;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Domain\CmsPages;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Fixture\YackCmsRevisionFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(YackCmsRevisionFixture::class)]
final class YackCmsRevisionFixtureTest extends TestCase
{
    public function testEveryPageAndTemplateStartsItsHistoryOnceWithoutAuthor(): void
    {
        $home = new CmsPage('accueil', 'Accueil');
        InMemoryCmsRevisions::identify($home, 1);
        $services = new CmsPage('services', 'Nos services');
        InMemoryCmsRevisions::identify($services, 2);
        $template = new CmsTemplate(CmsTemplateType::Product, 'Standard');
        InMemoryCmsRevisions::identify($template, 1);
        $revisions = new InMemoryCmsRevisions();
        $fixture = self::fixture([$home, $services], [$template], $revisions);

        $this->assertSame('yack_cms_revision', $fixture->getName());
        $fixture->load([]);
        $fixture->load([]);

        $this->assertCount(3, $revisions->stored);
        foreach ([$home, $services, $template] as $target) {
            $summaries = $revisions->summaries($target);
            $this->assertCount(1, $summaries);
            $this->assertNull($summaries[0]->author);
        }
    }

    /**
     * @param list<CmsPage>     $pages
     * @param list<CmsTemplate> $templates
     */
    private static function fixture(array $pages, array $templates, InMemoryCmsRevisions $revisions): YackCmsRevisionFixture
    {
        $pagesPort = self::createStub(CmsPages::class);
        $pagesPort->method('all')->willReturn($pages);
        $templatesPort = self::createStub(CmsTemplates::class);
        $templatesPort->method('all')->willReturn($templates);
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willReturnCallback($revisions->flush(...));

        return new YackCmsRevisionFixture($pagesPort, $templatesPort, $revisions, new RecordCmsRevision($revisions), $entityManager);
    }
}
