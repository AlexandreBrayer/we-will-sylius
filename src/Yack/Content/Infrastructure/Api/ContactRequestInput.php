<?php

declare(strict_types=1);

namespace App\Yack\Content\Infrastructure\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Yack\Content\Domain\ContactRequest;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /api/v2/shop/yack/contact-requests: message of the /contact page (public, JWT optional).
 * 202 without body: the message is e-mailed to Yack (nothing stored). 422 on a missing or invalid
 * field, 429 when the sender (IP address) reached the limit of config/packages/yack.yaml.
 */
#[ApiResource(
    shortName: 'YackContactRequest',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/contact-requests',
            status: 202,
            output: false,
            denormalizationContext: ['groups' => ['contact_request:write']],
            processor: ContactRequestProcessor::class,
            name: 'app_yack_api_shop_contact_request_post',
        ),
    ],
)]
final class ContactRequestInput
{
    #[Groups(['contact_request:write'])]
    #[Assert\NotBlank(message: 'Indiquez votre nom.', normalizer: 'trim')]
    #[Assert\Length(max: ContactRequest::NAME_MAX)]
    public ?string $name = null;

    #[Groups(['contact_request:write'])]
    #[Assert\NotBlank(message: 'Saisissez une adresse e-mail valide.', normalizer: 'trim')]
    #[Assert\Email(message: 'Saisissez une adresse e-mail valide.')]
    #[Assert\Length(max: ContactRequest::EMAIL_MAX)]
    public ?string $email = null;

    #[Groups(['contact_request:write'])]
    #[Assert\Length(max: ContactRequest::COMPANY_MAX)]
    public ?string $company = null;

    #[Groups(['contact_request:write'])]
    #[Assert\Length(max: ContactRequest::PHONE_MAX)]
    public ?string $phone = null;

    #[Groups(['contact_request:write'])]
    #[Assert\Length(max: ContactRequest::SUBJECT_MAX)]
    public ?string $subject = null;

    #[Groups(['contact_request:write'])]
    #[Assert\NotBlank(message: 'Écrivez votre message.', normalizer: 'trim')]
    #[Assert\Length(min: ContactRequest::MESSAGE_MIN, max: ContactRequest::MESSAGE_MAX, minMessage: 'Votre message doit comporter au moins {{ limit }} caractères.')]
    public ?string $message = null;

    public function toContactRequest(): ContactRequest
    {
        return ContactRequest::submit(
            (string) $this->name,
            (string) $this->email,
            $this->company,
            $this->phone,
            $this->subject,
            (string) $this->message,
        );
    }
}
