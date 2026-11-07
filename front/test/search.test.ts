// Tests of the header search suggestions logic (src/lib/search/suggest.ts): `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
	debounce,
	mediaPath,
	moveActive,
	noSuggestions,
	optionUrls,
	searchHref,
	shouldSuggest,
	SUGGEST_DELAY_MS,
	toSuggestions,
	type ApiSuggestions,
	type Timers
} from '../src/lib/search/suggest.ts';

/** Timers driven by the test: `tick(ms)` runs what is due. */
function fakeTimers() {
	let now = 0;
	let next = 0;
	const queue = new Map<number, { at: number; run: () => void }>();
	const timers: Timers = {
		set: (run, delay) => {
			queue.set(++next, { at: now + delay, run });
			return next;
		},
		clear: (handle) => queue.delete(handle as number)
	};
	const tick = (ms: number) => {
		now += ms;
		for (const [id, timer] of [...queue].sort((a, b) => a[1].at - b[1].at)) {
			if (timer.at <= now) {
				queue.delete(id);
				timer.run();
			}
		}
	};
	return { timers, tick, pending: () => queue.size };
}

describe('debounce', () => {
	it('runs once, with the last arguments, after the delay', () => {
		const { timers, tick } = fakeTimers();
		const calls: string[] = [];
		const suggest = debounce((q: string) => calls.push(q), SUGGEST_DELAY_MS, timers);

		suggest.call('cl');
		tick(150);
		suggest.call('cli');
		tick(150);
		suggest.call('clim');
		tick(199);
		assert.deepEqual(calls, []);
		tick(1);
		assert.deepEqual(calls, ['clim']);
	});

	it('drops the pending run on cancel', () => {
		const { timers, tick, pending } = fakeTimers();
		const calls: string[] = [];
		const suggest = debounce((q: string) => calls.push(q), 200, timers);

		suggest.call('pac');
		suggest.cancel();
		tick(500);
		assert.deepEqual(calls, []);
		assert.equal(pending(), 0);
	});

	it('waits with real timers too', async () => {
		const calls: number[] = [];
		const run = debounce((n: number) => calls.push(n), 5);
		run.call(1);
		run.call(2);
		await new Promise((resolve) => setTimeout(resolve, 30));
		assert.deepEqual(calls, [2]);
	});
});

describe('moveActive', () => {
	it('goes down from the field to the first option, then wraps around', () => {
		assert.equal(moveActive(-1, 3, 'ArrowDown'), 0);
		assert.equal(moveActive(1, 3, 'ArrowDown'), 2);
		assert.equal(moveActive(2, 3, 'ArrowDown'), 0);
	});

	it('goes up from the field or the first option to the last one', () => {
		assert.equal(moveActive(-1, 3, 'ArrowUp'), 2);
		assert.equal(moveActive(0, 3, 'ArrowUp'), 2);
		assert.equal(moveActive(2, 3, 'ArrowUp'), 1);
	});

	it('leaves the other keys to the field', () => {
		assert.equal(moveActive(1, 3, 'Home'), null);
		assert.equal(moveActive(1, 3, 'a'), null);
	});

	it('has no option to select in an empty list', () => {
		assert.equal(moveActive(-1, 0, 'ArrowDown'), -1);
	});
});

const API: ApiSuggestions = {
	query: 'clim',
	engine: 'meilisearch',
	products: [
		{ code: 'YK-CLM-P26', name: 'Climatiseur mural Prima 2,6 kW', slug: 'climatiseur-mural-prima-2-6-kw-yk-clm-p26', brand: 'Yack', image: 'http://nginx/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/prima.png' },
		{ code: 'SRK25ZSP-W1', name: 'Mural Premium 2,5 kW R32', slug: 'mural-premium', brand: null, image: null }
	],
	categories: [
		{ code: 'clim_murale', name: 'Climatiseurs muraux', slug: 'nos-produits/clik-yack/climatisation/muraux' },
		{ code: 'hors_menu', name: 'Hors menu', slug: 'hors-menu' }
	]
};

describe('toSuggestions', () => {
	const suggestions = toSuggestions(API, (code) => (code === 'clim_murale' ? '/produits/climatisation/muraux' : null));

	it('links the products to their page and keeps the thumbnail path on the shop origin', () => {
		assert.deepEqual(suggestions.products[0], {
			code: 'YK-CLM-P26',
			name: 'Climatiseur mural Prima 2,6 kW',
			brand: 'Yack',
			image: '/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/prima.png',
			url: '/produit/climatiseur-mural-prima-2-6-kw-yk-clm-p26'
		});
		assert.equal(suggestions.products[1].image, null);
	});

	it('keeps the categories of the shop menu only', () => {
		assert.deepEqual(suggestions.categories, [{ code: 'clim_murale', name: 'Climatiseurs muraux', url: '/produits/climatisation/muraux' }]);
		assert.equal(suggestions.query, 'clim');
	});

	it('orders the options as shown: products, categories, all the results', () => {
		assert.deepEqual(optionUrls(suggestions), [
			'/produit/climatiseur-mural-prima-2-6-kw-yk-clm-p26',
			'/produit/mural-premium',
			'/produits/climatisation/muraux',
			'/recherche?q=clim'
		]);
		assert.deepEqual(optionUrls(noSuggestions('pac')), ['/recherche?q=pac']);
	});
});

describe('helpers', () => {
	it('asks for suggestions from two characters', () => {
		assert.equal(shouldSuggest(' s '), false);
		assert.equal(shouldSuggest('ss'), true);
	});

	it('builds the results link with an encoded query', () => {
		assert.equal(searchHref(' pompe à chaleur '), '/recherche?q=pompe+%C3%A0+chaleur');
		assert.equal(searchHref('R32 & R290'), '/recherche?q=R32+%26+R290');
	});

	it('keeps a path as it is', () => {
		assert.equal(mediaPath('/media/x.png'), '/media/x.png');
		assert.equal(mediaPath('https://shop.example/media/x.png?v=1'), '/media/x.png');
	});
});
