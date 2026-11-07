<?php

declare(strict_types=1);

namespace App\Yack\AfterSales\Domain;

use App\Yack\Common\Domain\AiUnavailable;

/**
 * Port: reads the serial number and the model on the photo of a rating plate (function
 * "La pièce par une photo", IA-05). Adapters: deterministic demo reader by default, language model
 * when YACK_AI_ADAPTER=anthropic. A photo that cannot be read gives an unreadable reading, not an error.
 */
interface NameplateReader
{
    /** @throws AiUnavailable when the reading service cannot answer now */
    public function read(NameplatePhoto $photo): NameplateReading;
}
