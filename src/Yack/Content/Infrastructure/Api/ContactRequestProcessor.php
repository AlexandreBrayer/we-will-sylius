<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Yack\Content\Application\SubmitContactRequest;
use App\Yack\Content\Domain\ContactRequestInvalid;
use App\Yack\Content\Domain\ContactRequestThrottled;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The sender of the limit is the client IP as Symfony sees it (`Request::getClientIp()`): behind the
 * SvelteKit BFF it is the front container until `framework.trusted_proxies` and an X-Forwarded-For
 * from the BFF are set (see the MR of L0-04).
 *
 * @implements ProcessorInterface<ContactRequestInput, null>
 */
final readonly class ContactRequestProcessor implements ProcessorInterface
{
    public function __construct(private SubmitContactRequest $submitContactRequest)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $request = $context['request'] ?? null;
        $sender = $request instanceof Request ? ($request->getClientIp() ?? 'inconnu') : 'inconnu';

        try {
            ($this->submitContactRequest)($data->toContactRequest(), $sender);
        } catch (ContactRequestInvalid $invalid) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation($invalid->getMessage(), null, [], $data, $invalid->field, null),
            ]));
        } catch (ContactRequestThrottled $throttled) {
            throw new TooManyRequestsHttpException(null, $throttled->getMessage(), $throttled);
        }

        return null;
    }
}
