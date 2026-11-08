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
 * `libre` administrator changes a builder level; the server ignores a value forced by anyone else.
 *
 * @internal
 */
#[CoversClass(AdminUserTypeExtension::class)]
final class AdminUserTypeExtensionTest extends TestCase
{
    public function testTheFieldOffersTheThreeLevelsWithTheirFrenchLabels(): void
    {
        $view = $this->form(self::admin(BuilderLevel::Libre), new AdminUser())->createView();
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
        $form = $this->form(self::admin(BuilderLevel::Libre), new AdminUser());

        $this->assertSame(BuilderLevel::Charte, $form->get(AdminUserTypeExtension::FIELD)->getData());
        $this->assertSame('charte', $form->createView()->children[AdminUserTypeExtension::FIELD]->vars['value']);
    }

    public function testALibreAdministratorChangesTheLevelOfAnother(): void
    {
        $editor = self::admin(BuilderLevel::Charte);
        $form = $this->form(self::admin(BuilderLevel::Libre), $editor);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre_avertissement'], false);

        $this->assertTrue($form->get(AdminUserTypeExtension::FIELD)->isSynchronized());
        $this->assertSame(BuilderLevel::LibreAvertissement, $editor->getBuilderLevel());
    }

    #[DataProvider('levelsThatCannotManage')]
    public function testAnyOtherLevelSeesTheFieldReadOnlyAndCannotForceIt(BuilderLevel $current): void
    {
        $editor = self::admin(BuilderLevel::Charte);
        $form = $this->form(self::admin($current), $editor);

        $field = $form->createView()->children[AdminUserTypeExtension::FIELD];
        $this->assertTrue($field->vars['disabled']);
        $this->assertSame('Seul un administrateur de niveau Libre peut changer ce réglage.', $field->vars['help']);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], false);

        $this->assertSame(BuilderLevel::Charte, $editor->getBuilderLevel());
    }

    #[DataProvider('levelsThatCannotManage')]
    public function testAnAdministratorCannotRaiseTheirOwnLevel(BuilderLevel $current): void
    {
        $self = self::admin($current);
        $form = $this->form($self, $self);

        $form->submit([AdminUserTypeExtension::FIELD => 'libre'], true);

        $this->assertSame($current, $self->getBuilderLevel());
    }

    public function testWithoutALoggedInAdministratorTheFieldIsReadOnly(): void
    {
        $editor = self::admin(BuilderLevel::Charte);
        $factory = self::factory(new TokenStorage());
        $form = $factory->create(AdminUserType::class, $editor);

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

    private static function admin(BuilderLevel $level): AdminUser
    {
        $admin = new AdminUser();
        $admin->setUsername('test');
        $admin->setBuilderLevel($level);

        return $admin;
    }
}
