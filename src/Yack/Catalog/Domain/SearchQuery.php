<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * What the customer typed in the search box, normalized (single spaces, 100 characters at most),
 * with the page of results asked for, in the whole catalogue, one kind or one category (and its
 * subcategories, through the main category of each product). Out of range paging values are brought back in range: a
 * search never fails because of them.
 */
final readonly class SearchQuery
{
    public const MAX_LENGTH = 100;
    public const DEFAULT_PER_PAGE = 24;
    public const MAX_PER_PAGE = 100;

    /** Words kept for a database search (the rest of a long query is ignored). */
    public const MAX_TERMS = 6;

    public string $text;

    public int $perPage;

    public int $page;

    public function __construct(
        string $text,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1,
        public ?ProductKind $kind = null,
        public ?string $category = null,
    ) {
        $this->text = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, self::MAX_LENGTH);
        $this->perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $this->page = max(1, $page);
    }

    /**
     * Raw query string values (`q`, `limit`, `page`, `type`, `category`): a value that is not a
     * number keeps the default, an unknown type searches the whole catalogue, a category code that
     * is not one (letters, digits, `_`, `-`) is ignored.
     */
    public static function fromInput(?string $text, ?string $limit = null, ?string $page = null, ?string $type = null, ?string $category = null): self
    {
        $category = trim((string) $category);

        return new self(
            $text ?? '',
            self::integer($limit) ?? self::DEFAULT_PER_PAGE,
            self::integer($page) ?? 1,
            null === $type || '' === $type ? null : ProductKind::fromApiType($type),
            1 === preg_match('/^[\w-]{1,100}$/', $category) ? $category : null,
        );
    }

    public function isBlank(): bool
    {
        return '' === $this->text;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Distinct lower case words of the query, in order, at most MAX_TERMS.
     *
     * @return list<string>
     */
    public function terms(): array
    {
        $words = preg_split('/[\s,;]+/u', mb_strtolower($this->text), -1, \PREG_SPLIT_NO_EMPTY);

        return array_slice(array_values(array_unique(false === $words ? [] : $words)), 0, self::MAX_TERMS);
    }

    /**
     * One word mixing digits and at least three letters, as a product reference is typed ("SRK25",
     * "FDC224", "YKP-V4V"); a refrigerant ("R32", "R410A") or a size ("600x600") is not one.
     */
    public function looksLikeReference(): bool
    {
        $terms = $this->terms();

        return 1 === count($terms) && 1 === preg_match('/\d/', $terms[0]) && preg_match_all('/\p{L}/u', $terms[0]) >= 3;
    }

    private static function integer(?string $value): ?int
    {
        return null !== $value && 1 === preg_match('/^\s*\d{1,6}\s*$/', $value) ? (int) $value : null;
    }
}
