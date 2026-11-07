<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/**
 * Message left on the /contact page, sent to Yack by e-mail (nothing is stored).
 *
 * Built by `submit()`, which trims the values and refuses an incomplete or oversized message
 * (ContactRequestInvalid, with the field at fault) before anything leaves the application.
 */
final readonly class ContactRequest
{
    public const NAME_MAX = 120;

    public const EMAIL_MAX = 180;

    public const COMPANY_MAX = 255;

    public const PHONE_MAX = 32;

    public const SUBJECT_MAX = 150;

    public const MESSAGE_MIN = 10;

    public const MESSAGE_MAX = 5000;

    /** Subject used when the visitor leaves the field empty. */
    public const DEFAULT_SUBJECT = 'Demande de contact';

    private function __construct(
        public string $name,
        public string $email,
        public ?string $company,
        public ?string $phone,
        public string $subject,
        public string $message,
    ) {
    }

    /**
     * @throws ContactRequestInvalid
     */
    public static function submit(
        string $name,
        string $email,
        ?string $company,
        ?string $phone,
        ?string $subject,
        string $message,
    ): self {
        $name = self::singleLine($name);
        if ('' === $name) {
            throw ContactRequestInvalid::on('name', 'Indiquez votre nom.');
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            throw ContactRequestInvalid::on('name', sprintf('Votre nom ne doit pas dépasser %d caractères.', self::NAME_MAX));
        }

        $email = trim($email);
        if ('' === $email || mb_strlen($email) > self::EMAIL_MAX || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ContactRequestInvalid::on('email', 'Saisissez une adresse e-mail valide.');
        }

        $company = self::optional($company);
        if (null !== $company && mb_strlen($company) > self::COMPANY_MAX) {
            throw ContactRequestInvalid::on('company', sprintf('Le nom de la société ne doit pas dépasser %d caractères.', self::COMPANY_MAX));
        }

        $phone = self::optional($phone);
        if (null !== $phone && mb_strlen($phone) > self::PHONE_MAX) {
            throw ContactRequestInvalid::on('phone', sprintf('Le téléphone ne doit pas dépasser %d caractères.', self::PHONE_MAX));
        }

        $subject = self::optional($subject) ?? self::DEFAULT_SUBJECT;
        if (mb_strlen($subject) > self::SUBJECT_MAX) {
            throw ContactRequestInvalid::on('subject', sprintf('Le sujet ne doit pas dépasser %d caractères.', self::SUBJECT_MAX));
        }

        $message = trim($message);
        if (mb_strlen($message) < self::MESSAGE_MIN) {
            throw ContactRequestInvalid::on('message', sprintf('Votre message doit comporter au moins %d caractères.', self::MESSAGE_MIN));
        }
        if (mb_strlen($message) > self::MESSAGE_MAX) {
            throw ContactRequestInvalid::on('message', sprintf('Votre message ne doit pas dépasser %d caractères.', self::MESSAGE_MAX));
        }

        return new self($name, $email, $company, $phone, $subject, $message);
    }

    /** Trimmed value on one line (line breaks and tabs become spaces), null when empty. */
    private static function optional(?string $value): ?string
    {
        $value = self::singleLine((string) $value);

        return '' === $value ? null : $value;
    }

    private static function singleLine(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
    }
}
