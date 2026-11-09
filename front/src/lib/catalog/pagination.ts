// Compact pagination: first page, last page, and the pages around the current one, with a gap ('…')
// where pages are skipped: 1 … 4 5 [6] 7 8 … 33. A gap never stands for a single page (that page is shown
// instead), so the number of items stays stable while moving through the list.

export type PageItem = number | 'gap';

/**
 * @param current Current page (1-based), clamped to [1, total].
 * @param total Number of pages.
 * @param around Pages shown on each side of the current one.
 */
export function pageWindow(current: number, total: number, around = 2): PageItem[] {
	if (total < 1) {
		return [];
	}
	const page = Math.min(Math.max(current, 1), total);
	// Same number of items wherever the current page is: 1 2 3 4 5 6 7 … 33 near the start,
	// 1 … 4 5 6 7 8 … 33 in the middle. Few pages: all of them.
	const width = 2 * around + 1;
	if (total <= width + 4) {
		return Array.from({ length: total }, (_, i) => i + 1);
	}
	let start = page - around;
	let end = page + around;
	if (page <= around + 3) {
		start = 2;
		end = width + 2;
	} else if (page >= total - around - 2) {
		start = total - width - 1;
		end = total - 1;
	}

	const items: PageItem[] = [1];
	if (start > 2) {
		items.push('gap');
	}
	for (let n = start; n <= end; n++) {
		items.push(n);
	}
	if (end < total - 1) {
		items.push('gap');
	}
	if (total > 1) {
		items.push(total);
	}

	return items;
}
