<?php

declare(strict_types=1);

namespace App\Yack\Catalog\Domain;

/**
 * One page of a list (runs of the journal, lines of a run) and its compact pagination: first and last
 * pages, two pages on each side of the current one, null where pages are skipped (« … »).
 *
 * @template T
 */
final readonly class ResultPage
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }

    /** Page asked for, brought back between 1 and 10 000. */
    public static function number(string|int $page): int
    {
        $number = is_int($page) ? $page : (ctype_digit($page) ? (int) $page : 1);

        return max(1, min(10000, $number));
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** @return list<int|null> */
    public function window(): array
    {
        $pages = $this->pages();
        $window = [];
        $previous = 0;
        for ($page = 1; $page <= $pages; ++$page) {
            if (1 !== $page && $pages !== $page && abs($page - $this->page) > 2) {
                continue;
            }
            if ($page - $previous > 1) {
                $window[] = null;
            }
            $window[] = $page;
            $previous = $page;
        }

        return $window;
    }
}
