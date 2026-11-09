<?php

declare(strict_types=1);

namespace App\Yack\Content\Domain;

/** A file or a text refused by the media library (422, French message shown as is by the builder). */
final class CmsMediaRefused extends \DomainException
{
    public static function missing(): self
    {
        return new self('Choisissez un fichier image.');
    }

    public static function interrupted(): self
    {
        return new self('L\'envoi du fichier a échoué : réessayez.');
    }

    public static function empty(): self
    {
        return new self('Le fichier est vide.');
    }

    public static function tooLarge(): self
    {
        return new self('Fichier trop volumineux (20 Mo maximum).');
    }

    public static function svg(): self
    {
        return new self('Les images SVG ne sont pas acceptées : envoyez un JPEG, un PNG ou un WebP.');
    }

    public static function format(): self
    {
        return new self('Format non accepté : JPEG, PNG ou WebP uniquement.');
    }

    public static function unreadable(): self
    {
        return new self('Image illisible : le fichier est peut-être abîmé.');
    }

    public static function tooManyPixels(): self
    {
        return new self(sprintf('Image trop grande (%d mégapixels au plus) : réduisez-la avant de l\'envoyer.', intdiv(CmsMediaFile::MAX_PIXELS, 1_000_000)));
    }

    public static function altTooLong(): self
    {
        return new self(sprintf('Le texte alternatif fait au plus %d caractères.', CmsMediaFile::ALT_MAX));
    }
}
