<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

/** Outcome of the identification of an appliance from the photo of its rating plate or label. */
enum IdentificationStatus: string
{
    /** Serial number read and known: same page as a manual search (/sav/{serial}). */
    case Found = 'found';
    /** No known serial number, but a model reference of the catalog: its product sheet and documentation. */
    case Product = 'product';
    /** Nothing usable read on the photo. */
    case Unreadable = 'unreadable';
    /** Serial number or reference read, absent from the installed base and from the catalog. */
    case Unknown = 'unknown';
    /** The reading service cannot answer now (real adapter only). */
    case Unavailable = 'unavailable';
}
