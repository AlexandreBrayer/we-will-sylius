<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Content\CmsTemplate;
use App\Entity\User\AdminUser;
use App\Tests\Yack\Content\Application\InMemoryCmsEditLocks;
use App\Tests\Yack\Content\Application\InMemoryCmsPages;
use App\Tests\Yack\Content\Application\InMemoryCmsRevisions;
use App\Tests\Yack\Content\Application\InMemoryCmsTemplates;
use App\Yack\Content\Application\CmsEditLocking;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CmsEditLockAction;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\BuilderLevelCheck;
use App\Yack\Content\Infrastructure\Api\CmsEditLockGuard;
use App\Yack\Content\Infrastructure\Api\CmsEditLockTargets;
use App\Yack\Content\Infrastructure\Api\CmsTemplateProcessor;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 */
#[CoversClass(CmsTemplateProcessor::class)]
final class CmsTemplateProcessorTest extends TestCase
{
    private InMemoryCmsRevisions $revisions;

    private InMemoryCmsEditLocks $locks;

    #[\Override]
    protected function setUp(): void
    {
        $this->revisions = new InMemoryCmsRevisions();
        $this->locks = new InMemoryCmsEditLocks();
    }

    public function testAnEditorCannotSaveANewFreeValueAndNoTemplateIsTouched(): void
    {
        $other = self::template(true);
        [$template, $context] = self::put([self::buyBox()], [self::buyBox('120px')]);
        $template->setStatus(CmsPageStatus::Published);
        $template->setDefault(true);

        try {
            $this->processor(BuilderLevel::Charte, [$other, $template], $this->neverCalled())->process($template, new Put(), [], $context);
            $this->fail('ValidationException expected');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            $this->assertSame('blocks', $violation->getPropertyPath());
            $this->assertSame('Bloc n°1 : l\'espacement 120px est hors charte. Votre compte ne permet pas de sortir de la charte.', $violation->getMessage());
        }
        $this->assertTrue($other->isDefault());
        $this->assertSame([], $this->revisions->pending);
    }

    public function testAnAdministratorSavesTheSameRequest(): void
    {
        [$template, $context] = self::put([self::buyBox()], [self::buyBox('120px')]);

        $this->assertSame($template, $this->processor(BuilderLevel::Libre, [], $this->calledOnceWith($template))->process($template, new Put(), [], $context));
    }

    public function testAnEditorKeepsAStoredFreeValueAndMovesItsBlock(): void
    {
        $gallery = ['type' => 'produit-galerie', 'props' => []];
        [$template, $context] = self::put([$gallery, self::buyBox('120px')], [self::buyBox('120px'), $gallery]);

        $this->assertSame($template, $this->processor(BuilderLevel::Charte, [], $this->calledOnceWith($template))->process($template, new Put(), [], $context));
    }

    public function testAnEditorCannotCreateATemplateWithAFreeValue(): void
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        $template->setBlocks([self::buyBox('120px')]);

        $this->expectException(ValidationException::class);
        $this->processor(BuilderLevel::Charte, [], $this->neverCalled())->process($template, new Post(), [], ['previous_data' => null]);
    }

    public function testAnAcceptedSaveRecordsOneRevisionWrittenByTheSameFlush(): void
    {
        $other = self::template(true);
        [$template, $context] = self::put([self::buyBox()], [self::buyBox('l')]);
        $template->setStatus(CmsPageStatus::Published);
        $template->setDefault(true);
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->once())->method('process')->willReturnCallback(function (CmsTemplate $saved): CmsTemplate {
            $this->assertCount(1, $this->revisions->pending, 'the revision is pending when the template is flushed');

            return $saved;
        });

        $this->processor(BuilderLevel::Charte, [$other, $template], $persist)->process($template, new Put(), [], $context);

        $this->assertFalse($other->isDefault());
        $this->assertCount(1, $this->revisions->pending, 'the template that only loses the default flag gets no revision');
        $this->assertSame($template, $this->revisions->pending[0]->getTemplate());
        $this->assertSame($template->getBlocks(), $this->revisions->pending[0]->getContent()['blocks']);
        $this->assertSame(['hautEspace' => 'l'], $template->getBlocks()[0]['style'] ?? null);
        $this->assertTrue($this->revisions->pending[0]->getContent()['isDefault']);
    }

    public function testRemovingTheDefaultFlagIsRefusedWithoutRevision(): void
    {
        $template = self::template(true);
        $previous = clone $template;
        $template->setDefault(false);

        $this->expectException(ValidationException::class);

        try {
            $this->processor(BuilderLevel::Libre, [], $this->neverCalled())->process($template, new Put(), [], ['previous_data' => $previous]);
        } finally {
            $this->assertSame([], $this->revisions->pending);
        }
    }

    public function testATemplateLockedByAnotherAdministratorIsNotSavedAndNoOtherTemplateIsTouched(): void
    {
        $other = self::template(true);
        $template = self::template(false);
        (new \ReflectionProperty(CmsTemplate::class, 'id'))->setValue($template, 4);
        $previous = clone $template;
        $template->setDefault(true);
        $paul = new AdminUser();
        (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($paul, 2);
        $paul->setFirstName('Paul');
        $paul->setLastName('Durand');
        (new CmsEditLocking($this->locks, new MockClock()))->apply(CmsEditLockAction::Acquire, $template, $paul);

        try {
            $this->processor(BuilderLevel::Libre, [$other, $template], $this->neverCalled())->process($template, new Put(), ['id' => 4], ['previous_data' => $previous]);
            $this->fail('ConflictHttpException expected');
        } catch (ConflictHttpException $conflict) {
            $this->assertStringStartsWith('Paul Durand modifie ce template en ce moment', $conflict->getMessage());
        }
        $this->assertTrue($other->isDefault());
        $this->assertSame([], $this->revisions->pending);
    }

    public function testTheDefaultTemplateCannotBeDeletedWhateverTheLevel(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->processor(BuilderLevel::Libre, [], $this->neverCalled())->process(self::template(true), new Delete());
    }

    /**
     * @param list<array<string, mixed>> $stored
     * @param list<array<string, mixed>> $sent
     *
     * @return array{CmsTemplate, array{previous_data: CmsTemplate}}
     */
    private static function put(array $stored, array $sent): array
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Fiche');
        $template->setBlocks($stored);
        $previous = clone $template;
        $template->setBlocks($sent);

        return [$template, ['previous_data' => $previous]];
    }

    private static function template(bool $default): CmsTemplate
    {
        $template = new CmsTemplate(CmsTemplateType::Product, 'Défaut');
        $template->setBlocks([self::buyBox()]);
        $template->setStatus(CmsPageStatus::Published);
        $template->setDefault($default);

        return $template;
    }

    /**
     * @param list<CmsTemplate>                            $all
     * @param ProcessorInterface<CmsTemplate, CmsTemplate> $persist
     */
    private function processor(BuilderLevel $level, array $all, ProcessorInterface $persist): CmsTemplateProcessor
    {
        $admin = new AdminUser();
        (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($admin, 1);
        $admin->setBuilderLevel($level);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));
        $templates = $this->createStub(CmsTemplates::class);
        $templates->method('all')->willReturn($all);
        $remove = $this->createMock(ProcessorInterface::class);
        $remove->expects($this->never())->method('process');

        $guard = new CmsEditLockGuard(new CmsEditLocking($this->locks, new MockClock()), new CmsEditLockTargets(new InMemoryCmsPages(), new InMemoryCmsTemplates(), $tokens));

        return new CmsTemplateProcessor($templates, $guard, new BuilderLevelCheck($tokens), new RecordCmsRevision($this->revisions), new CurrentRevisionAuthor($tokens), $persist, $remove);
    }

    /**
     * @return ProcessorInterface<CmsTemplate, CmsTemplate>
     */
    private function neverCalled(): ProcessorInterface
    {
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->never())->method('process');

        return $persist;
    }

    /**
     * @return ProcessorInterface<CmsTemplate, CmsTemplate>
     */
    private function calledOnceWith(CmsTemplate $template): ProcessorInterface
    {
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->once())->method('process')->with($template)->willReturn($template);

        return $persist;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buyBox(string $spaceAbove = ''): array
    {
        return ['type' => 'produit-achat', 'props' => [], 'style' => '' === $spaceAbove ? [] : ['hautEspace' => $spaceAbove]];
    }
}
