<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Yack\Content\Domain\BuilderLevel;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;
use Sylius\MolliePlugin\Entity\OnboardingStatusAwareInterface;
use Sylius\MolliePlugin\Entity\OnboardingStatusAwareTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_admin_user')]
class AdminUser extends BaseAdminUser implements OnboardingStatusAwareInterface
{
    use OnboardingStatusAwareTrait;

    /**
     * Freedom of the administrator in the CMS builder (App\Yack\Content\Domain\BuilderLevel), set in the
     * back-office form (Content\Infrastructure\Form\AdminUserTypeExtension): only a `libre` administrator
     * may change it. Not exposed by the Sylius administrator API.
     */
    #[ORM\Column(name: 'builder_level', type: 'string', length: 32, enumType: BuilderLevel::class, options: ['default' => 'charte'])]
    private BuilderLevel $builderLevel = BuilderLevel::Charte;

    public function getBuilderLevel(): BuilderLevel
    {
        return $this->builderLevel;
    }

    public function setBuilderLevel(BuilderLevel $builderLevel): void
    {
        $this->builderLevel = $builderLevel;
    }
}
