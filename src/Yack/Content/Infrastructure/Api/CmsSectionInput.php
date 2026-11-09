<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use Symfony\Component\Serializer\Attribute\Groups;

/** Body of POST /admin/cms-sections (CmsSectionView): checked by Application\CreateCmsSection. */
final class CmsSectionInput
{
    #[Groups(['cms_section:create'])]
    public ?string $name = null;

    /** @var array<mixed>|null */
    #[Groups(['cms_section:create'])]
    public ?array $blocks = null;
}
