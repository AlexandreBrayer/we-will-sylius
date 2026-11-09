// Compact pagination (src/lib/catalog/pagination.ts): first and last page, window around the current one,
// gaps that never hide a single page. `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { pageWindow } from '../src/lib/catalog/pagination.ts';

const render = (current: number, total: number) =>
	pageWindow(current, total)
		.map((item) => (item === 'gap' ? '…' : String(item)))
		.join(' ');

describe('pageWindow', () => {
	it('shows every page when there are few', () => {
		assert.equal(render(1, 1), '1');
		assert.equal(render(2, 7), '1 2 3 4 5 6 7');
		assert.equal(render(4, 9), '1 2 3 4 5 6 7 8 9');
	});

	it('keeps a stable width near the edges', () => {
		assert.equal(render(1, 33), '1 2 3 4 5 6 7 … 33');
		assert.equal(render(5, 33), '1 2 3 4 5 6 7 … 33');
		assert.equal(render(33, 33), '1 … 27 28 29 30 31 32 33');
		assert.equal(render(29, 33), '1 … 27 28 29 30 31 32 33');
	});

	it('frames the current page with gaps in the middle', () => {
		assert.equal(render(6, 33), '1 … 4 5 6 7 8 … 33');
		assert.equal(render(17, 33), '1 … 15 16 17 18 19 … 33');
	});

	it('never hides a single page behind a gap', () => {
		for (let total = 1; total <= 40; total++) {
			for (let current = 1; current <= total; current++) {
				const items = pageWindow(current, total);
				const numbers = items.filter((item): item is number => item !== 'gap');
				assert.ok(numbers.includes(current) && numbers.includes(1) && numbers.includes(total), `${current}/${total}`);
				items.forEach((item, i) => {
					if (item === 'gap') {
						assert.ok((items[i + 1] as number) - (items[i - 1] as number) > 2, `${current}/${total}`);
					}
				});
			}
		}
	});

	it('clamps a page out of range', () => {
		assert.equal(render(50, 33), render(33, 33));
		assert.equal(render(0, 33), render(1, 33));
	});
});
