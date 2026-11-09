<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** A page, a template or a reusable section whose blocks show an image of the library. */
final readonly class CmsMediaUsage
{
    public const PAGE = 'page';

    public const TEMPLATE = 'template';

    public const SECTION = 'section';

    /**
     * @param self::PAGE|self::TEMPLATE|self::SECTION $kind
     * @param string                                  $id   slug of a page, id of a template or of a section
     */
    public function __construct(
        public string $kind,
        public string $id,
        public string $title,
    ) {
    }

    public function describe(): string
    {
        return sprintf('%s « %s »', $this->kind, $this->title);
    }
}
