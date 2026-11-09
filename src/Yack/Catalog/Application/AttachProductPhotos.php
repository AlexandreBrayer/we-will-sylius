<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Application;

use App\Yack\Catalog\Domain\ProductPhotos;
use App\Yack\Catalog\Domain\ProductSource;
use App\Yack\Catalog\Domain\ProductSourceUnavailable;

/**
 * Gives their photo to the products loaded with a generic visual, once the photo files are there (they are
 * downloaded apart and may arrive later). Only the image changes: prices, stocks, orders and accounts stay as they
 * are, and running it again changes nothing. The products touched are then refreshed in the search index.
 */
final readonly class AttachProductPhotos
{
    public function __construct(
        private ProductSource $source,
        private ProductPhotos $photos,
        private SearchIndexerInterface $searchIndexer,
    ) {
    }

    /**
     * @throws ProductSourceUnavailable
     */
    public function __invoke(string $feed): PhotoAttachment
    {
        $attached = [];
        $alreadyRight = 0;
        $withoutPhoto = 0;
        foreach ($this->source->fetch($feed)->products as $product) {
            if (!$product->hasPhoto() || null === $product->imagePath || null === $product->fallbackImagePath) {
                ++$withoutPhoto;

                continue;
            }

            if ($this->photos->replaceFallback($product->code, $product->imagePath, $product->fallbackImagePath)) {
                $attached[] = $product->code;
            } else {
                ++$alreadyRight;
            }
        }

        if ([] !== $attached) {
            $this->searchIndexer->refresh($attached);
        }

        return new PhotoAttachment($attached, $alreadyRight, $withoutPhoto);
    }
}
