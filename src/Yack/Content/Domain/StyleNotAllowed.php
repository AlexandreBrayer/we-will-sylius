<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * A block style holds a value outside the charte and the builder level of the author does not
 * allow it (BuilderLevel::allowsFreeValues()). Distinct from InvalidPageBlocks (format or
 * bounds): the content is well formed, the account is not allowed to write it.
 */
final class StyleNotAllowed extends \DomainException
{
    public function __construct(public readonly StyleDeviation $deviation)
    {
        parent::__construct(sprintf(
            'Bloc n°%d : %s est hors charte. Votre compte ne permet pas de sortir de la charte.',
            $deviation->block,
            $deviation->describe(),
        ));
    }
}
