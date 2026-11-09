<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Tests\Yack\Content\Application\InMemoryCmsRevisions;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\BuilderLevelCheck;
use App\Yack\Content\Infrastructure\Api\CmsTemplateDuplicateProcessor;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 */
#[CoversClass(CmsTemplateDuplicateProcessor::class)]
final class CmsTemplateDuplicateProcessorTest extends TestCase
{
    private InMemoryCmsRevisions $revisions;

    #[\Override]
    protected function setUp(): void
    {
        $this->revisions = new InMemoryCmsRevisions();
    }

    public function testTheCopyIsADraftWithTheSameContent(): void
    {
        $source = self::source('teal');

        $copy = $this->processor(BuilderLevel::Charte, 1)->process($source, new Post());

        $this->assertNotSame($source, $copy);
        $this->assertSame('Copie de Fiche', $copy->getName());
        $this->assertSame($source->getBlocks(), $copy->getBlocks());
        $this->assertCount(1, $this->revisions->pending);
        $this->assertSame($copy, $this->revisions->pending[0]->getTemplate());
        $this->assertSame('Copie de Fiche', $this->revisions->pending[0]->getTitle());
    }

    public function testAnEditorCannotCopyATemplateThatHoldsAFreeValue(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('blocks: Bloc n°1 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.');

        try {
            $this->processor(BuilderLevel::Charte, 0)->process(self::source('#ff7700'), new Post());
        } finally {
            $this->assertSame([], $this->revisions->pending);
        }
    }

    public function testAnAdministratorCopiesIt(): void
    {
        $copy = $this->processor(BuilderLevel::Libre, 1)->process(self::source('#ff7700'), new Post());

        $this->assertSame(['fond' => '#ff7700'], $copy->getBlocks()[0]['style'] ?? null);
    }

    private static function source(string $background): CmsTemplate
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        $template->setBlocks([['type' => 'produit-achat', 'props' => [], 'style' => ['fond' => $background]]]);

        return $template;
    }

    private function processor(BuilderLevel $level, int $writes): CmsTemplateDuplicateProcessor
    {
        $admin = new AdminUser();
        $admin->setBuilderLevel($level);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));
        $templates = $this->createMock(CmsTemplates::class);
        $templates->expects($this->exactly($writes))->method('add');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly($writes))->method('flush')->willReturnCallback(function (): void {
            $this->assertCount(1, $this->revisions->pending, 'the revision of the copy is written by the same flush');
        });

        return new CmsTemplateDuplicateProcessor($templates, new BuilderLevelCheck($tokens), new RecordCmsRevision($this->revisions), new CurrentRevisionAuthor($tokens), $entityManager);
    }
}
