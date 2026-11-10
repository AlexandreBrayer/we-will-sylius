<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * A record of a batch that cannot be applied, with the reason shown to an administrator and the record
 * as received (`payload`, in the format of the Yack API feed), kept to be corrected and replayed.
 */
final readonly class RejectedRecord
{
    /**
     * @param array<string, mixed> $payload record as received, empty when it was not even an object
     * @param int|null             $line    line of the file, for a manual import
     */
    public function __construct(
        public string $reference,
        public string $reason,
        public array $payload = [],
        public ?int $line = null,
    ) {
    }

    /** Name of the product in the record, when it has one. */
    public function name(): ?string
    {
        $label = $this->payload['label'] ?? null;

        return is_string($label) && '' !== trim($label) ? trim($label) : null;
    }
}
