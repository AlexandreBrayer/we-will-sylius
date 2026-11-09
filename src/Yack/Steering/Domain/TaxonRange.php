<?php

declare(strict_types=1);

namespace App\Yack\Steering\Domain;

/**
 * Range (gamme) of a taxon: the taxon of the first level under the root of its catalogue branch.
 */
final readonly class TaxonRange
{
    public function __construct(
        public string $rootCode,
        public string $code,
        public string $name,
    ) {
    }
}
