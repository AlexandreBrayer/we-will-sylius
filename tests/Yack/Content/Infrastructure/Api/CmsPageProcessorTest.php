<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Content\CmsPage;
use App\Entity\User\AdminUser;
use App\Tests\Yack\Content\Application\InMemoryCmsRevisions;
use App\Yack\Content\Application\RecordCmsRevision;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Infrastructure\Api\BuilderLevelCheck;
use App\Yack\Content\Infrastructure\Api\CmsPageProcessor;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * PUT as API Platform runs it with `standard_put: false`: `previous_data` is a clone of the stored
 * page, then the request body is written into the page itself.
 *
 * @internal
 */
#[CoversClass(CmsPageProcessor::class)]
final class CmsPageProcessorTest extends TestCase
{
    private const REFUSED = 'Bloc n°2 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.';

    public function testAnEditorCannotSaveANewFreeColourAndThePageIsNotWritten(): void
    {
        [$page, $context] = self::put([self::cta(), self::textImage()], [self::cta(), self::textImage('#ff7700')]);

        $this->expectRefusal(self::REFUSED);
        self::processor(BuilderLevel::Charte, $this->neverWrites())->process($page, new Put(), ['slug' => 'offre'], $context);
    }

    public function testAnAdministratorSavesTheSameRequest(): void
    {
        [$page, $context] = self::put([self::cta(), self::textImage()], [self::cta(), self::textImage('#ff7700')]);

        $saved = self::processor(BuilderLevel::Libre, $this->writesOnce($page))->process($page, new Put(), ['slug' => 'offre'], $context);

        $this->assertSame(['fond' => '#ff7700'], $saved->getBlocks()[1]['style'] ?? null);
    }

    public function testAnAdministratorWithWarningSavesItToo(): void
    {
        [$page, $context] = self::put([self::cta()], [self::cta(), self::textImage('#ff7700')]);

        $this->assertSame($page, self::processor(BuilderLevel::LibreAvertissement, $this->writesOnce($page))->process($page, new Put(), [], $context));
    }

    public function testAnEditorSavesAPageThatAlreadyHoldsAFreeValueWithoutTouchingIt(): void
    {
        [$page, $context] = self::put(
            [self::cta(), self::textImage('#ff7700')],
            [self::cta('Nouveau titre'), self::textImage('#ff7700'), self::textImage('teal')],
        );
        $page->setTitle('Offre de printemps');

        $this->assertSame($page, self::processor(BuilderLevel::Charte, $this->writesOnce($page))->process($page, new Put(), [], $context));
    }

    public function testAnEditorCannotChangeTheFreeValue(): void
    {
        [$page, $context] = self::put([self::cta(), self::textImage('#ff7700')], [self::cta(), self::textImage('#ff0000')]);

        $this->expectRefusal('Bloc n°2 : la couleur #ff0000 est hors charte. Votre compte ne permet pas de sortir de la charte.');
        self::processor(BuilderLevel::Charte, $this->neverWrites())->process($page, new Put(), [], $context);
    }

    public function testAnEditorMovesTheBlockThatHoldsTheFreeValue(): void
    {
        [$page, $context] = self::put([self::cta(), self::textImage('#ff7700')], [self::textImage('#ff7700'), self::cta()]);

        $this->assertSame($page, self::processor(BuilderLevel::Charte, $this->writesOnce($page))->process($page, new Put(), [], $context));
    }

    public function testAnEditorCannotDuplicateTheBlockThatHoldsTheFreeValue(): void
    {
        [$page, $context] = self::put([self::cta(), self::textImage('#ff7700')], [self::cta(), self::textImage('#ff7700'), self::textImage('#ff7700')]);

        $this->expectRefusal('Bloc n°3 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.');
        self::processor(BuilderLevel::Charte, $this->neverWrites())->process($page, new Put(), [], $context);
    }

    public function testAnEditorCannotCreateAPageWithAFreeValue(): void
    {
        $page = new CmsPage('offre', 'Offre');
        $page->setBlocks([self::cta(), self::textImage('#ff7700')]);

        $this->expectRefusal(self::REFUSED);
        self::processor(BuilderLevel::Charte, $this->neverWrites())->process($page, new Post(), [], ['previous_data' => null]);
    }

    public function testAnAcceptedSaveRecordsARevisionWrittenByTheSameFlush(): void
    {
        [$page, $context] = self::put([self::cta()], [self::cta('Nouveau titre'), self::textImage('teal')]);
        $revisions = new InMemoryCmsRevisions();
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->once())->method('process')->willReturnCallback(function (CmsPage $saved) use ($revisions): CmsPage {
            $this->assertCount(1, $revisions->pending, 'the revision is pending when the page is flushed');

            return $saved;
        });

        self::processor(BuilderLevel::Charte, $persist, $revisions)->process($page, new Put(), [], $context);

        $revision = $revisions->pending[0];
        $this->assertSame($page, $revision->getPage());
        $this->assertSame($page->getBlocks(), $revision->getContent()['blocks']);
        $this->assertSame('Sophie Martin', $revision->author()?->name);
    }

    public function testARefusedSaveRecordsNoRevision(): void
    {
        [$page, $context] = self::put([self::cta()], [self::cta(), self::textImage('#ff7700')]);
        $revisions = new InMemoryCmsRevisions();

        try {
            self::processor(BuilderLevel::Charte, $this->neverWrites(), $revisions)->process($page, new Put(), [], $context);
            $this->fail('ValidationException expected');
        } catch (ValidationException) {
            $this->assertSame([], $revisions->pending);
        }
    }

    /**
     * @param list<array<string, mixed>> $stored
     * @param list<array<string, mixed>> $sent
     *
     * @return array{CmsPage, array{previous_data: CmsPage}}
     */
    private static function put(array $stored, array $sent): array
    {
        $page = new CmsPage('offre', 'Offre');
        $page->setBlocks($stored);
        $previous = clone $page;
        $page->setBlocks($sent);

        return [$page, ['previous_data' => $previous]];
    }

    /**
     * @param ProcessorInterface<CmsPage, CmsPage> $persist
     */
    private static function processor(BuilderLevel $level, ProcessorInterface $persist, ?InMemoryCmsRevisions $revisions = null): CmsPageProcessor
    {
        $admin = new AdminUser();
        $admin->setBuilderLevel($level);
        $admin->setFirstName('Sophie');
        $admin->setLastName('Martin');
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));

        return new CmsPageProcessor(new BuilderLevelCheck($tokens), new RecordCmsRevision($revisions ?? new InMemoryCmsRevisions()), new CurrentRevisionAuthor($tokens), $persist);
    }

    /**
     * @return ProcessorInterface<CmsPage, CmsPage>
     */
    private function neverWrites(): ProcessorInterface
    {
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->never())->method('process');

        return $persist;
    }

    /**
     * @return ProcessorInterface<CmsPage, CmsPage>
     */
    private function writesOnce(CmsPage $page): ProcessorInterface
    {
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects($this->once())->method('process')->with($page)->willReturn($page);

        return $persist;
    }

    private function expectRefusal(string $message): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('blocks: ' . $message);
    }

    /**
     * @return array<string, mixed>
     */
    private static function cta(string $title = 'Vous êtes installateur ?'): array
    {
        return ['type' => 'cta', 'props' => ['title' => $title]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textImage(string $background = ''): array
    {
        return ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => '' === $background ? [] : ['fond' => $background]];
    }
}
