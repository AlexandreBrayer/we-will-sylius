// Tests of the listing query read from the URL (src/lib/types.ts `listingSearch`, `priceRange`), of the
// listing links (src/lib/catalog/listingUrl.ts) and of the price range filter (src/lib/catalog/priceRange.ts):
// `npm test`. The page size is not a URL parameter: it comes from the category template or the listing defaults.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isSelected, listingUrl, toggleValue } from '../src/lib/catalog/listingUrl.ts';
import { formatEuros, priceChanges, priceLabel, priceScale } from '../src/lib/catalog/priceRange.ts';
import { listingSearch, priceRange } from '../src/lib/types.ts';

const search = (query: string) => listingSearch(new URL(`http://boutique.test/produits/climatisation${query}`));
/** French amounts: narrow no-break space between thousands, no-break space before "€". */
const NNBSP = '\u202f';
const NBSP = '\u00a0';

describe('listingSearch', () => {
	it('reads the search, the sort, the page and the facet filters', () => {
		assert.deepEqual(search('?q=gainable&tri=nom-asc&page=2&marque=Daikin&gamme=Pro&puissance=6-9&fluide=R32'), {
			q: 'gainable',
			tri: 'nom-asc',
			page: '2',
			marque: ['Daikin'],
			gamme: ['Pro'],
			puissance: ['6-9'],
			fluide: ['R32']
		});
	});

	it('reads the repeated values of a facet, sorted and each once', () => {
		assert.deepEqual(search('?marque=RHOSS&marque=MHI&marque=&marque=RHOSS&puissance=12%2B&puissance=0-3'), {
			marque: ['MHI', 'RHOSS'],
			puissance: ['0-3', '12+']
		});
		// Same listing state whatever the order of the URL: same query cache entry.
		assert.deepEqual(search('?marque=MHI&gamme=Pro&marque=RHOSS'), search('?gamme=Pro&marque=RHOSS&marque=MHI'));
	});

	it('ignores ?par= of an old link, alone or with other parameters', () => {
		assert.deepEqual(search('?par=18'), {});
		assert.deepEqual(search('?par=27&tri=recent&page=3'), { tri: 'recent', page: '3' });
	});

	it('leaves out absent and empty parameters', () => {
		assert.deepEqual(search(''), {});
		assert.deepEqual(search('?q=&tri=&page=&marque=&fluide='), {});
	});

	it('reads the price range as typed', () => {
		assert.deepEqual(search('?prix_min=500&prix_max=2000&marque=MHI'), { prix_min: '500', prix_max: '2000', marque: ['MHI'] });
		assert.deepEqual(search('?prix_min=&prix_max='), {});
	});
});

describe('priceRange', () => {
	it('reads euros, a comma or spaces included', () => {
		assert.deepEqual(priceRange({ prix_min: '500', prix_max: '1 999,90' }), { min: 500, max: 1999.9 });
		assert.deepEqual(priceRange({ prix_max: '0' }), { min: null, max: 0 });
		assert.deepEqual(priceRange({}), { min: null, max: null });
	});

	it('drops an invalid or negative bound, keeps the other', () => {
		assert.deepEqual(priceRange({ prix_min: 'abc', prix_max: '2000' }), { min: null, max: 2000 });
		assert.deepEqual(priceRange({ prix_min: '-5', prix_max: 'Infinity' }), { min: null, max: null });
	});

	it('puts bounds typed the other way round back in order', () => {
		assert.deepEqual(priceRange({ prix_min: '2000', prix_max: '500' }), { min: 500, max: 2000 });
	});
});

describe('price range filter', () => {
	it('scales the slider in whole steps around the prices of the listing', () => {
		assert.deepEqual(priceScale({ min: 69.9, max: 9847.9 }), { min: 60, max: 9850, step: 10 });
		assert.deepEqual(priceScale({ min: 12.5, max: 480.2 }), { min: 12, max: 481, step: 1 });
		// One price: the scale still spans one step.
		assert.deepEqual(priceScale({ min: 300, max: 300 }), { min: 300, max: 301, step: 1 });
	});

	it('leaves out a bound at the end of the scale or empty, keeps the other filters', () => {
		const scale = { min: 60, max: 9850, step: 10 };
		assert.deepEqual(priceChanges(500, 2000, scale), { prix_min: '500', prix_max: '2000' });
		assert.deepEqual(priceChanges(60, 9850, scale), { prix_min: '', prix_max: '' });
		assert.deepEqual(priceChanges(null, 1999.5, scale), { prix_min: '', prix_max: '1999.5' });
		const current = { pathname: '/produits/pac', search: '?marque=MHI&marque=Yack&page=3&prix_max=9000' };
		assert.equal(
			listingUrl(current, priceChanges(500, 2000, scale), { tri: 'position', page: '1' }),
			'/produits/pac?marque=MHI&marque=Yack&prix_min=500&prix_max=2000'
		);
		assert.equal(listingUrl(current, priceChanges(null, null, scale)), '/produits/pac?marque=MHI&marque=Yack');
	});

	it('labels the active range in euros HT', () => {
		assert.equal(priceLabel({ min: 500, max: 2000 }), `500${NBSP}€ à 2${NNBSP}000${NBSP}€ HT`);
		assert.equal(priceLabel({ min: 1499.9, max: null }), `Dès 1${NNBSP}499,9${NBSP}€ HT`);
		assert.equal(priceLabel({ min: null, max: 2000 }), `Jusqu'à 2${NNBSP}000${NBSP}€ HT`);
		assert.equal(priceLabel({ min: null, max: null }), null);
		assert.equal(formatEuros(69.9), `69,9${NBSP}€`);
	});
});

describe('listing links', () => {
	const url = (query: string) => ({ pathname: '/produits/pac', search: query });
	const initial = { tri: 'position', page: '1' };

	it('adds a facet value next to the selected ones, back to page 1', () => {
		const current = url('?marque=MHI&tri=nom-asc&page=3');
		assert.equal(listingUrl(current, { marque: toggleValue(['MHI'], 'RHOSS') }, initial), '/produits/pac?tri=nom-asc&marque=MHI&marque=RHOSS');
	});

	it('removes one value and keeps the others', () => {
		const current = url('?marque=MHI&marque=RHOSS&gamme=Pro');
		assert.equal(listingUrl(current, { marque: toggleValue(['MHI', 'RHOSS'], 'mhi') }, initial), '/produits/pac?gamme=Pro&marque=RHOSS');
		assert.equal(listingUrl(url('?marque=MHI&gamme=Pro'), { marque: toggleValue(['MHI'], 'MHI') }, initial), '/produits/pac?gamme=Pro');
	});

	it('clears every filter at once', () => {
		const current = url('?marque=MHI&marque=RHOSS&puissance=0-3&q=split');
		assert.equal(listingUrl(current, { marque: [], gamme: [], puissance: [], fluide: [] }, initial), '/produits/pac?q=split');
	});

	it('keeps the filters on the other pages, leaves out initial values and ?par=', () => {
		const current = url('?marque=MHI&marque=RHOSS&par=18');
		assert.equal(listingUrl(current, { page: 2 }, initial), '/produits/pac?marque=MHI&marque=RHOSS&page=2');
		assert.equal(listingUrl(current, { page: 1, tri: 'position' }, initial), '/produits/pac?marque=MHI&marque=RHOSS');
		assert.equal(listingUrl(url('?q=split'), { q: '' }, initial), '/produits/pac');
	});

	it('compares the facet values case aside', () => {
		assert.equal(isSelected(['MHI'], 'mhi'), true);
		assert.equal(isSelected(undefined, 'MHI'), false);
		assert.deepEqual(toggleValue(undefined, 'R32'), ['R32']);
	});
});
