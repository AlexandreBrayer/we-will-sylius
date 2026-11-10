<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/** One field of a product before and after a record of Yack, as shown to an administrator (null: empty). */
final readonly class FieldChange
{
    public function __construct(
        public string $field,
        public string $label,
        public ?string $before,
        public ?string $after,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): ?string => isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;

        return new self($text('field') ?? '', $text('label') ?? '', $text('before'), $text('after'));
    }

    /** @return array{field: string, label: string, before: string|null, after: string|null} */
    public function toArray(): array
    {
        return ['field' => $this->field, 'label' => $this->label, 'before' => $this->before, 'after' => $this->after];
    }
}
