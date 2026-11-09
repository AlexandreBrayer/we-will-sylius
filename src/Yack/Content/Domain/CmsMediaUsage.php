<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** A page or a template whose blocks show an image of the library. */
final readonly class CmsMediaUsage
{
    public const PAGE = 'page';

    public const TEMPLATE = 'template';

    /**
     * @param self::PAGE|self::TEMPLATE $kind
     * @param string                    $id   slug of a page, id of a template
     */
    public function __construct(
        public string $kind,
        public string $id,
        public string $title,
    ) {
    }

    public function describe(): string
    {
        return sprintf('%s « %s »', self::PAGE === $this->kind ? 'page' : 'template', $this->title);
    }
}
