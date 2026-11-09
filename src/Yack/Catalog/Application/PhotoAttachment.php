<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

/**
 * Outcome of AttachProductPhotos: photos put in place, products already right, products without a photo file
 * (photo not downloaded yet, or no photo declared, like the spare parts and their generic visuals).
 */
final readonly class PhotoAttachment
{
    /**
     * @param list<string> $attached codes of the products that received their photo
     */
    public function __construct(
        public array $attached,
        public int $alreadyRight,
        public int $withoutPhoto,
    ) {
    }
}
