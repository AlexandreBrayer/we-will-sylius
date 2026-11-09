// Tests of the listing query read from the URL (src/lib/types.ts `listingSearch`): `npm test`.
// The page size is not a URL parameter: it comes from the category template or the listing defaults.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { listingSearch } from '../src/lib/types.ts';

const search = (query: string) => listingSearch(new URL(`http://boutique.test/produits/climatisation${query}`));

describe('listingSearch', () => {
	it('reads the search, the sort, the page and the facet filters', () => {
		assert.deepEqual(search('?q=gainable&tri=nom-asc&page=2&marque=Daikin&gamme=Pro&puissance=6-9&fluide=R32'), {
			q: 'gainable',
			tri: 'nom-asc',
			page: '2',
			marque: 'Daikin',
			gamme: 'Pro',
			puissance: '6-9',
			fluide: 'R32'
		});
	});

	it('ignores ?par= of an old link, alone or with other parameters', () => {
		assert.deepEqual(search('?par=18'), {});
		assert.deepEqual(search('?par=27&tri=recent&page=3'), { tri: 'recent', page: '3' });
	});

	it('leaves out absent and empty parameters', () => {
		assert.deepEqual(search(''), {});
		assert.deepEqual(search('?q=&tri=&page='), {});
	});
});
