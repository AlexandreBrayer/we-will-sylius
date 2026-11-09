<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Content\Domain\CmsSectionRefused;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/** A refused section as API Platform answers a validation error: 422, one violation on `name` or `blocks`. */
final class CmsSectionViolation
{
    public static function of(CmsSectionRefused $refused, object $root): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($refused->getMessage(), null, [], $root, $refused->field, null),
        ]));
    }
}
