<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Form;

use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\BuilderLevel;
use Sylius\Bundle\AdminBundle\Form\Type\AdminUserType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * BO administrator form: « Niveau dans le builder » (AdminUser::$builderLevel), section added by
 * sylius_twig_hooks (config/packages/yack.yaml). Only a `libre` administrator changes it
 * (BuilderLevel::canManageLevels()), and never on their own record (same identifier as the
 * logged-in administrator), so at least one `libre` administrator always remains. Otherwise the
 * field is disabled, so Symfony ignores a value forced in the request and keeps the stored level.
 * The field is added once the edited administrator is known (PRE_SET_DATA); a new administrator
 * has no identifier yet, so it is never « their own record ».
 */
final class AdminUserTypeExtension extends AbstractTypeExtension
{
    public const FIELD = 'builderLevel';

    public const HELP_MANAGE = 'Charte : couleurs et tailles de la charte seulement. Libre avec avertissement : valeurs libres, signalées dans le builder. Libre : valeurs libres, et réglage du niveau des autres administrateurs.';

    public const HELP_NOT_LIBRE = 'Seul un administrateur de niveau Libre peut changer ce réglage.';

    public const HELP_OWN_RECORD = 'Vous ne pouvez pas changer votre propre niveau.';

    public function __construct(private readonly TokenStorageInterface $tokens)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $edited = $data instanceof AdminUser ? $data : null;
            $help = $this->readOnlyReason($edited);

            $event->getForm()->add(self::FIELD, EnumType::class, [
                'class' => BuilderLevel::class,
                'choice_label' => static fn (BuilderLevel $level): string => $level->label(),
                'label' => 'Niveau dans le builder',
                'help' => $help ?? self::HELP_MANAGE,
                'disabled' => null !== $help,
                // A request without the field keeps the stored level (new administrator: charte).
                'empty_data' => ($edited?->getBuilderLevel() ?? BuilderLevel::Charte)->value,
            ]);
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [AdminUserType::class];
    }

    /** Null when the logged-in administrator may change the level of $edited, else the French reason shown under the field. */
    private function readOnlyReason(?AdminUser $edited): ?string
    {
        $current = $this->tokens->getToken()?->getUser();
        if (!$current instanceof AdminUser || !$current->getBuilderLevel()->canManageLevels()) {
            return self::HELP_NOT_LIBRE;
        }

        return self::isSameAdministrator($current, $edited) ? self::HELP_OWN_RECORD : null;
    }

    /**
     * Same record when both have an identifier and it is the same. A logged-in administrator without
     * an identifier cannot be told apart from the edited one: treated as the same (read-only).
     */
    private static function isSameAdministrator(AdminUser $current, ?AdminUser $edited): bool
    {
        $currentId = self::identifier($current);
        if (null === $currentId) {
            return true;
        }

        return $currentId === self::identifier($edited);
    }

    private static function identifier(?AdminUser $admin): ?string
    {
        $id = $admin?->getId();

        return \is_int($id) || (\is_string($id) && '' !== $id) ? (string) $id : null;
    }
}
