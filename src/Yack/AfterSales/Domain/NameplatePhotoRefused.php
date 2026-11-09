<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/** The uploaded photo cannot be read: empty, too large, HEIC or not a JPEG / PNG / WebP (type read on the content). */
final class NameplatePhotoRefused extends \DomainException
{
    public static function empty(): self
    {
        return new self('Le fichier est vide.');
    }

    public static function tooLarge(): self
    {
        return new self('Fichier trop volumineux (20 Mo maximum)');
    }

    public static function heic(): self
    {
        return new self('Photo HEIC non lue : envoyez-la en JPEG, PNG ou WebP.');
    }

    public static function format(): self
    {
        return new self('Format non accepté (JPEG, PNG ou WebP).');
    }
}
