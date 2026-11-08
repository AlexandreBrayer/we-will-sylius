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
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Domain\CmsPageStatus;
use App\Yack\Content\Domain\CmsTemplates;
use App\Yack\Content\Domain\CmsTemplateType;
use App\Yack\Content\Infrastructure\Api\BuilderLevelCheck;
use App\Yack\Content\Infrastructure\Api\CmsTemplateProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 */
#[CoversClass(CmsTemplateProcessor::class)]
final class CmsTemplateProcessorTest extends TestCase
{
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
        $admin->setBuilderLevel($level);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));
        $templates = $this->createStub(CmsTemplates::class);
        $templates->method('all')->willReturn($all);
        $remove = $this->createMock(ProcessorInterface::class);
        $remove->expects($this->never())->method('process');

        return new CmsTemplateProcessor($templates, new BuilderLevelCheck($tokens), $persist, $remove);
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
