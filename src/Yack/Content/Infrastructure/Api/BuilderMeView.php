<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\User\AdminUser;
use App\Yack\Content\Domain\Charte;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * GET /api/v2/admin/yack/builder/me (JWT of a Sylius administrator, ROLE_API_ACCESS; 401 without it):
 * the logged-in administrator as the builder sees them, with their level (BuilderLevel) and the whole
 * charte (Charte::toArray()), so that the builder never copies the charte.
 */
#[ApiResource(
    shortName: 'YackBuilderMe',
    operations: [
        new Get(
            uriTemplate: '/admin/yack/builder/me',
            normalizationContext: ['groups' => ['builder_me:item']],
            security: "is_granted('ROLE_API_ACCESS')",
            provider: BuilderMeProvider::class,
            name: 'app_yack_api_admin_builder_me_get',
        ),
    ],
)]
final readonly class BuilderMeView
{
    /**
     * @param array{colors: list<array{key: string, label: string, value: string}>, titleSizes: list<array{key: string, label: string, value: string}>, spacings: list<array{key: string, label: string, value: string}>, radii: list<array{key: string, label: string, value: string}>} $charte
     */
    public function __construct(
        /** charte | libre_avertissement | libre */
        #[Groups(['builder_me:item'])]
        public string $level,
        /** French label of the level (« Charte », « Libre avec avertissement », « Libre »). */
        #[Groups(['builder_me:item'])]
        public string $levelLabel,
        #[Groups(['builder_me:item'])]
        public ?string $firstName,
        /** Colours, title sizes, spacings and radii of the charte: lists of {key, label, value}. */
        #[Groups(['builder_me:item'])]
        public array $charte,
    ) {
    }

    public static function of(AdminUser $admin): self
    {
        $level = $admin->getBuilderLevel();

        return new self($level->value, $level->label(), $admin->getFirstName(), Charte::toArray());
    }
}
