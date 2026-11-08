<?php

declare(strict_types=1);

namespace App\Tests\Yack\Content\Infrastructure\Form;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use App\Yack\Content\Infrastructure\Form\AdminUserTypeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Sylius\Bundle\CoreBundle\Form\Type\User\AdminUserType as CoreAdminUserType;
use Sylius\Bundle\CoreBundle\Form\Type\User\AvatarImageType;
use Sylius\Component\Core\Model\AvatarImage;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The real back-office form (Sylius AdminUserType and its parents) with the extension: D3, only a
 * `libre` administrator changes a builder level, never their own (same identifier); the server
 * ignores a value forced by anyone else or on one's own record.
 *
 * @internal
 */
#[CoversClass(AdminUserTypeExtension::class)]
final class AdminUserTypeExtensionTest extends TestCase
{
    public function testTheFieldOffersTheThreeLevelsWithTheirFrenchLabels(): void
    {
        $view = $this->form(self::admin(BuilderLevel::Libre, 1), new AdminUser())->createView();
        $field = $view->children[AdminUserTypeExtension::FIELD];

        $this->assertSame('Niveau dans le builder', $field->vars['label']);
        $this->assertSame(
            ['charte' => 'Charte', 'libre_avertissement' => 'Libre avec avertissement', 'libre' => 'Libre'],
            array_column(array_map(static fn ($choice): array => [$choice->value, $choice->label], $field->vars['choices']), 1, 0),
        );
        $this->assertFalse($field->vars['disabled']);
    }

    public function testANewAdministratorStartsAtTheCharteLevel(): void
    {
        $form = $this->form(self::admin(BuilderLevel::Libre, 1), new AdminUser());

        $this->assertFalse($form->get(AdminUserTypeExtension::FIELD)->isDisabled());
        $this->assertSame(BuilderLevel::Charte, $form->get(AdminUserTypeExtension::FIELD)->getData());
        $this->assertSame('charte', $form->createView()->children[AdminUserTypeExtension::FIELD]->vars['value']);
    }

    public function testALibreAdministratorChangesTheLevelOfAnother(): void
    {
        $editor = self::admin(BuilderLevel::Charte, 2);
        $form = $this->form(self::admin(BuilderLevel::Libre, 1), $editor);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre_avertissement'], false);

        $this->assertTrue($form->get(AdminUserTypeExtension::FIELD)->isSynchronized());
        $this->assertSame(BuilderLevel::LibreAvertissement, $editor->getBuilderLevel());
    }

    #[DataProvider('levelsThatCannotManage')]
    public function testAnyOtherLevelSeesTheFieldReadOnlyAndCannotForceIt(BuilderLevel $current): void
    {
        $editor = self::admin(BuilderLevel::Charte, 2);
        $form = $this->form(self::admin($current, 1), $editor);

        $field = $form->createView()->children[AdminUserTypeExtension::FIELD];
        $this->assertTrue($field->vars['disabled']);
        $this->assertSame(AdminUserTypeExtension::HELP_NOT_LIBRE, $field->vars['help']);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], false);

        $this->assertSame(BuilderLevel::Charte, $editor->getBuilderLevel());
    }

    #[DataProvider('levelsThatCannotManage')]
    public function testAnAdministratorCannotRaiseTheirOwnLevel(BuilderLevel $current): void
    {
        $self = self::admin($current, 1);
        $form = $this->form($self, $self);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], true);

        $this->assertSame($current, $self->getBuilderLevel());
    }

    public function testWithoutALoggedInAdministratorTheFieldIsReadOnly(): void
    {
        $editor = self::admin(BuilderLevel::Charte, 2);
        $factory = self::factory(new TokenStorage());
        $form = $factory->create(AdminUserType::class, $editor);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], false);

        $this->assertTrue($form->get(AdminUserTypeExtension::FIELD)->isDisabled());
        $this->assertSame(BuilderLevel::Charte, $editor->getBuilderLevel());
    }

    #[DataProvider('allLevels')]
    public function testNobodyChangesTheirOwnLevelEvenWhenForcedInTheRequest(BuilderLevel $current, string $help): void
    {
        // The session user and the edited entity are distinct objects with the same identifier.
        $edited = self::admin($current, 7);
        $form = $this->form(self::admin($current, 7), $edited);

        $field = $form->createView()->children[AdminUserTypeExtension::FIELD];
        $this->assertTrue($field->vars['disabled']);
        $this->assertSame($help, $field->vars['help']);

        $forced = BuilderLevel::Libre === $current ? 'charte' : 'libre';
        $form->submit([AdminUserTypeExtension::FIELD => $forced], false);

        $this->assertSame($current, $edited->getBuilderLevel());
    }

    public function testALibreAdministratorOnTheirOwnRecordReadsTheFrenchMention(): void
    {
        $self = self::admin(BuilderLevel::Libre, 1);
        $field = $this->form($self, $self)->createView()->children[AdminUserTypeExtension::FIELD];

        $this->assertTrue($field->vars['disabled']);
        $this->assertSame('Vous ne pouvez pas changer votre propre niveau.', $field->vars['help']);
        $this->assertSame('libre', $field->vars['value']);
    }

    public function testTheOwnRecordIsToldByIdentifierNotByEmail(): void
    {
        $current = self::admin(BuilderLevel::Libre, 1, 'meme@yack.test');
        $other = self::admin(BuilderLevel::Charte, 2, 'meme@yack.test');
        $form = $this->form($current, $other);

        $this->assertFalse($form->get(AdminUserTypeExtension::FIELD)->isDisabled());
        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], false);

        $this->assertSame(BuilderLevel::Libre, $other->getBuilderLevel());
    }

    public function testALibreAdministratorCreatesAnAdministratorAtTheChosenLevel(): void
    {
        $created = new AdminUser();
        $form = $this->form(self::admin(BuilderLevel::Libre, 1), $created);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre_avertissement'], false);

        $this->assertSame(BuilderLevel::LibreAvertissement, $created->getBuilderLevel());
    }

    public function testACreationFormWithoutDataStaysEditableForALibreAdministrator(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(self::admin(BuilderLevel::Libre, 1), 'admin', ['ROLE_ADMINISTRATION_ACCESS']));
        $form = self::factory($tokens)->create(AdminUserType::class);

        $this->assertFalse($form->get(AdminUserTypeExtension::FIELD)->isDisabled());
        $this->assertSame(AdminUserTypeExtension::HELP_MANAGE, $form->createView()->children[AdminUserTypeExtension::FIELD]->vars['help']);
    }

    public function testALoggedInAdministratorWithoutIdentifierIsNotTrustedWithLevels(): void
    {
        $editor = self::admin(BuilderLevel::Charte, 2);
        $form = $this->form(self::admin(BuilderLevel::Libre), $editor);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], false);

        $this->assertTrue($form->get(AdminUserTypeExtension::FIELD)->isDisabled());
        $this->assertSame(BuilderLevel::Charte, $editor->getBuilderLevel());
    }

    public function testItExtendsTheBackOfficeAdministratorForm(): void
    {
        $this->assertSame([AdminUserType::class], [...AdminUserTypeExtension::getExtendedTypes()]);
    }

    /** @return \Iterator<string, array{BuilderLevel}> */
    public static function levelsThatCannotManage(): \Iterator
    {
        yield 'charte' => [BuilderLevel::Charte];
        yield 'libre avec avertissement' => [BuilderLevel::LibreAvertissement];
    }

    /** @return \Iterator<string, array{BuilderLevel, string}> */
    public static function allLevels(): \Iterator
    {
        yield 'charte' => [BuilderLevel::Charte, AdminUserTypeExtension::HELP_NOT_LIBRE];
        yield 'libre avec avertissement' => [BuilderLevel::LibreAvertissement, AdminUserTypeExtension::HELP_NOT_LIBRE];
        yield 'libre' => [BuilderLevel::Libre, AdminUserTypeExtension::HELP_OWN_RECORD];
    }

    /** @return FormInterface<mixed> */
    private function form(AdminUser $current, AdminUser $edited): FormInterface
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($current, 'admin', ['ROLE_ADMINISTRATION_ACCESS']));

        return self::factory($tokens)->create(AdminUserType::class, $edited);
    }

    private static function factory(TokenStorage $tokens): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypes([new CoreAdminUserType(AdminUser::class), new AdminUserType(), new AvatarImageType(AvatarImage::class)])
            ->addTypeExtension(new AdminUserTypeExtension($tokens))
            ->getFormFactory();
    }

    private static function admin(BuilderLevel $level, ?int $id = null, string $email = 'test@yack.test'): AdminUser
    {
        $admin = new AdminUser();
        $admin->setUsername('test');
        $admin->setEmail($email);
        $admin->setBuilderLevel($level);
        if (null !== $id) {
            (new \ReflectionProperty(AdminUser::class, 'id'))->setValue($admin, $id);
        }

        return $admin;
    }
}
