<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

enum CmsPageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Published => 'Publiée',
        };
    }
}
