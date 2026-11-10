// Tests of the listing query read from the URL (src/lib/types.ts `listingSearch`) and of the listing links
// (src/lib/catalog/listingUrl.ts): `npm test`.
// The page size is not a URL parameter: it comes from the category template or the listing defaults.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isSelected, listingUrl, toggleValue } from '../src/lib/catalog/listingUrl.ts';
import { listingSearch } from '../src/lib/types.ts';

const search = (query: string) => listingSearch(new URL(`http://boutique.test/produits/climatisation${query}`));

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
