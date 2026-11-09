<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User\AdminUser;
use App\Tests\Yack\Content\Application\InMemoryCmsMediaLibrary;
use App\Tests\Yack\Content\Application\InMemoryCmsSections;
use App\Yack\Content\Application\CreateCmsSection;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Infrastructure\Api\BuilderLevelCheck;
use App\Yack\Content\Infrastructure\Api\CmsSectionCreateProcessor;
use App\Yack\Content\Infrastructure\Api\CmsSectionInput;
use App\Yack\Content\Infrastructure\Security\CurrentRevisionAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 */
#[CoversClass(CmsSectionCreateProcessor::class)]
final class CmsSectionCreateProcessorTest extends TestCase
{
    private const ORANGE = ['type' => 'texte-image', 'props' => ['image' => 'yack/cms/hero.jpg'], 'style' => ['fond' => '#ff7700']];

    public function testTheSectionIsSavedWithTheLoggedInAdministratorAsAuthor(): void
    {
        $sections = new InMemoryCmsSections();

        $view = self::processor($sections, self::tokens(BuilderLevel::Libre))->process(self::input('Bandeau promo', [self::ORANGE]), new Post());

        $this->assertSame([1, 'Bandeau promo', 1, 'Sophie Martin', ['fond' => '#ff7700']], [$view->id, $view->name, $view->blockCount, $view->author, $view->blocks[0]['style']]);
        $this->assertCount(1, $sections->sections);
    }

    public function testTheLevelOfTheAdministratorApplies(): void
    {
        $sections = new InMemoryCmsSections();

        try {
            self::processor($sections, self::tokens(BuilderLevel::Charte))->process(self::input('Orange', [self::ORANGE]), new Post());
            $this->fail('A Charte account cannot save a free value.');
        } catch (ValidationException $refused) {
            $this->assertSame('blocks: Bloc n°1 : la couleur #ff7700 est hors charte. Votre compte ne permet pas de sortir de la charte.', $refused->getMessage());
        }
        $this->assertSame([], $sections->sections);
    }

    public function testWithoutAdministratorTheLevelIsLibreAndThereIsNoAuthor(): void
    {
        $view = self::processor(new InMemoryCmsSections(), new TokenStorage())->process(self::input('Orange', [self::ORANGE]), new Post());

        $this->assertNull($view->author);
    }

    public function testAMissingNameIsA422OnTheName(): void
    {
        $input = new CmsSectionInput();

        try {
            self::processor(new InMemoryCmsSections(), self::tokens(BuilderLevel::Libre))->process($input, new Post());
            $this->fail('A section without name must be refused.');
        } catch (ValidationException $refused) {
            $violation = $refused->getConstraintViolationList()->get(0);
            $this->assertSame(['name', 'Donnez un nom à la section.', $input], [$violation->getPropertyPath(), $violation->getMessage(), $violation->getRoot()]);
        }
    }

    public function testMissingBlocksAreA422OnTheBlocks(): void
    {
        try {
            self::processor(new InMemoryCmsSections(), self::tokens(BuilderLevel::Libre))->process(self::input('Vide', null), new Post());
            $this->fail('A section without block must be refused.');
        } catch (ValidationException $refused) {
            $this->assertSame('blocks: Une section contient au moins un bloc.', $refused->getMessage());
        }
    }

    /**
     * @param array<mixed>|null $blocks
     */
    private static function input(?string $name, ?array $blocks): CmsSectionInput
    {
        $input = new CmsSectionInput();
        $input->name = $name;
        $input->blocks = $blocks;

        return $input;
    }

    private static function tokens(BuilderLevel $level): TokenStorage
    {
        $admin = new AdminUser();
        $admin->setFirstName('Sophie');
        $admin->setLastName('Martin');
        $admin->setBuilderLevel($level);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($admin, 'api_admin', ['ROLE_API_ACCESS']));

        return $tokens;
    }

    private static function processor(InMemoryCmsSections $sections, TokenStorage $tokens): CmsSectionCreateProcessor
    {
        return new CmsSectionCreateProcessor(new CreateCmsSection($sections, new InMemoryCmsMediaLibrary()), new BuilderLevelCheck($tokens), new CurrentRevisionAuthor($tokens));
    }
}
