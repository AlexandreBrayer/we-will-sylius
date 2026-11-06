<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api\Registration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Account\Application\Registration\RegisterCustomer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<RegistrationInput, RegistrationResult>
 */
final readonly class RegistrationProcessor implements ProcessorInterface
{
    public function __construct(private RegisterCustomer $registerCustomer)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RegistrationResult
    {
        $email = (string) $data->contact->email;
        if ($this->registerCustomer->isEmailTaken($email)) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(
                'Un compte existe déjà avec cette adresse e-mail. Connectez-vous ou utilisez « Mot de passe oublié ».',
                null,
                [],
                $data,
                'contact.email',
                $email,
            )]));
        }

        $customer = ($this->registerCustomer)($data->toRegistrationData(
            $this->registerCustomer->hashPassword((string) $data->contact->password),
        ));

        return new RegistrationResult((string) $customer->getEmail(), (string) $data->profile);
    }
}
