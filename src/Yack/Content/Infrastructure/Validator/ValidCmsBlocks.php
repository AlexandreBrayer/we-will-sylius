<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Blocks of a CmsPage or a CmsTemplate valid for PageBlocks::normalize(), images of the media library
 * included (ValidCmsBlocksValidator). Violation on `blocks` (422 through API Platform).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidCmsBlocks extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
