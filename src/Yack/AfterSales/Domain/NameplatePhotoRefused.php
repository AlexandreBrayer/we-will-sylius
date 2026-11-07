<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

/** The uploaded photo cannot be read: empty, too large or not a JPEG / PNG (type read on the content). */
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

    public static function format(): self
    {
        return new self('Format non accepté (JPG, JPEG, PNG).');
    }
}
