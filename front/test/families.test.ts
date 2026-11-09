// Tests of the family pills of the "categorie-sous-categories" block (src/lib/blocks/families.ts): `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { ALL_LABEL, familyPills } from '../src/lib/blocks/families.ts';

const families = [
	{ code: 'parts_cartes', name: 'Cartes & électronique', url: '/pieces/cartes' },
	{ code: 'parts_compresseurs', name: 'Compresseurs', url: '/pieces/compresseurs' },
	{ code: 'parts_ventilation', name: 'Ventilation', url: '/pieces/ventilation' }
];
const root = { taxon: { code: 'clik_yack_parts', url: '/pieces' }, children: families, siblings: [], up: null };
const family = { taxon: { code: 'parts_compresseurs', url: '/pieces/compresseurs' }, children: [], siblings: families, up: '/pieces' };
const shown = (pills: ReturnType<typeof familyPills>) => pills.map((p) => `${p.current ? '✓ ' : ''}${p.name} ${p.url}`);

describe('familyPills', () => {
	it('top of the section: the "all" pill first and active, then every family', () => {
		assert.deepEqual(shown(familyPills(root, 'Toutes les pièces')), [
			'✓ Toutes les pièces /pieces',
			'Cartes & électronique /pieces/cartes',
			'Compresseurs /pieces/compresseurs',
			'Ventilation /pieces/ventilation'
		]);
	});

	it('page of a family: the same row, the family checked, "all" back to the parent', () => {
		const pills = familyPills(family, 'Toutes les pièces');
		assert.deepEqual(shown(pills), [
			'Toutes les pièces /pieces',
			'Cartes & électronique /pieces/cartes',
			'✓ Compresseurs /pieces/compresseurs',
			'Ventilation /pieces/ventilation'
		]);
		assert.deepEqual(
			pills.map((p) => p.all),
			[true, false, false, false]
		);
		assert.equal(pills.filter((p) => p.current).length, 1);
	});

	it('an empty label falls back to "Tout voir"', () => {
		const pills = familyPills(root, '  ');
		assert.equal(pills[0].name, ALL_LABEL);
	});

	it('nothing at the top of a section without sub-categories', () => {
		assert.deepEqual(familyPills({ ...root, children: [] }), []);
	});
});
