<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

use App\Yack\Common\Domain\AiUnavailable;

/**
 * Port: reads the serial number, the model reference and the model name on the photo of a rating plate
 * or of the label of an appliance (function « La pièce par une photo »). Adapters: deterministic demo
 * reader by default, language model when YACK_AI_ADAPTER names a provider. A photo that cannot be read
 * gives an unreadable reading, not an error.
 */
interface NameplateReader
{
    /** @throws AiUnavailable when the reading service cannot answer now */
    public function read(NameplatePhoto $photo): NameplateReading;
}
