<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

/** Outcome of the identification of an appliance from the photo of its rating plate. */
enum IdentificationStatus: string
{
    /** Serial number read and known: same page as a manual search (/sav/{serial}). */
    case Found = 'found';
    /** Nothing usable read on the photo. */
    case Unreadable = 'unreadable';
    /** Serial number read but absent from the installed base. */
    case Unknown = 'unknown';
    /** The reading service cannot answer now (real adapter only). */
    case Unavailable = 'unavailable';
}
