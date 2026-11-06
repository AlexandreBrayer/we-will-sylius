<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ValidSiret extends Constraint
{
    public string $message = 'Le SIRET doit comporter 14 chiffres valides (vérifiez la saisie, clé de contrôle incorrecte).';
}
