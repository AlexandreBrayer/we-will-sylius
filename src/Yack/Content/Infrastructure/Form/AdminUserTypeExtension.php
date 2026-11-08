<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Form;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * BO administrator form: « Niveau dans le builder » (AdminUser::$builderLevel), section added by
 * sylius_twig_hooks (config/packages/yack.yaml). Only a `libre` administrator changes it
 * (BuilderLevel::canManageLevels()); for any other one the field is disabled, so Symfony ignores a
 * value forced in the request and keeps the stored level.
 */
final class AdminUserTypeExtension extends AbstractTypeExtension
{
    public const FIELD = 'builderLevel';

    public function __construct(private readonly TokenStorageInterface $tokens)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $canManage = $this->currentAdminCanManageLevels();

        $builder->add(self::FIELD, EnumType::class, [
            'class' => BuilderLevel::class,
            'choice_label' => static fn (BuilderLevel $level): string => $level->label(),
            'label' => 'Niveau dans le builder',
            'help' => $canManage
                ? 'Charte : couleurs et tailles de la charte seulement. Libre avec avertissement : valeurs libres, signalées dans le builder. Libre : valeurs libres, et réglage du niveau des autres administrateurs.'
                : 'Seul un administrateur de niveau Libre peut changer ce réglage.',
            'disabled' => !$canManage,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminUserType::class];
    }

    private function currentAdminCanManageLevels(): bool
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AdminUser && $user->getBuilderLevel()->canManageLevels();
    }
}
