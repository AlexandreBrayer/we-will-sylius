<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Application;

use App\Yack\AfterSales\Domain\NameplatePhoto;
use App\Yack\AfterSales\Domain\NameplateReader;
use App\Yack\AfterSales\Domain\NameplateReading;

/** Type lu sur le contenu (finfo), contrôle de la photo (`NameplatePhoto::of`), puis lecture par le port `NameplateReader`. */
final readonly class ReadNameplate implements ReadNameplateInterface
{
    public function __construct(private NameplateReader $reader)
    {
    }

    public function read(string $bytes): NameplateReading
    {
        $mediaType = '' === $bytes ? '' : (string) (new \finfo(\FILEINFO_MIME_TYPE))->buffer($bytes);

        return $this->reader->read(NameplatePhoto::of($bytes, $mediaType));
    }
}
