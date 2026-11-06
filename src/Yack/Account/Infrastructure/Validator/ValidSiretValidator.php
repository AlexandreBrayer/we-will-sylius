<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Validator;

use App\Yack\Account\Domain\Siret;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidSiretValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSiret) {
            throw new UnexpectedTypeException($constraint, ValidSiret::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value) || !Siret::isValid($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
