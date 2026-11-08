// Tests of the documentation assistant mapping (src/lib/documentation/types.ts): `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { NOT_FOUND, showsPanel, sourceLabel, toAnswer, toCoverage } from '../src/lib/documentation/types.ts';

describe('toAnswer', () => {
	it('keeps the answer, its sources and the simulated flag', () => {
		const answer = toAnswer({
			answer: ' La longueur maximale de la liaison hydraulique est de 25 m. ',
			sources: [{ source: "Notice d'installation Evolys 8 kW (FICTIF)", page: 12 }],
			simulated: true
		});
		assert.deepEqual(answer, {
			answer: 'La longueur maximale de la liaison hydraulique est de 25 m.',
			sources: [{ source: "Notice d'installation Evolys 8 kW (FICTIF)", page: 12 }],
			simulated: true
		});
	});

	it('drops malformed sources and never shows an answer without source', () => {
		assert.deepEqual(toAnswer({ answer: '25 m.', sources: [{ source: '', page: 1 }, { source: 'N', page: 0 }, 'x'] }), {
			answer: NOT_FOUND,
			sources: [],
			simulated: false
		});
		assert.deepEqual(toAnswer(null), { answer: NOT_FOUND, sources: [], simulated: false });
		assert.deepEqual(toAnswer({ answer: '', sources: [{ source: 'N', page: 2 }] }).sources, []);
	});

	it('passes the not-found answer of the API through', () => {
		assert.deepEqual(toAnswer({ answer: NOT_FOUND, sources: [], simulated: false }), { answer: NOT_FOUND, sources: [], simulated: false });
	});
});

describe('toCoverage', () => {
	it('gives the sample question only when the product has a notice', () => {
		assert.deepEqual(toCoverage({ productCode: 'YK-PAE-M08', notices: ['Notice Evolys'], exampleQuestion: 'Longueur ?' }, 'YK-PAE-M08'), {
			productCode: 'YK-PAE-M08',
			notices: ['Notice Evolys'],
			exampleQuestion: 'Longueur ?'
		});
		assert.deepEqual(toCoverage({ notices: [], exampleQuestion: 'Longueur ?' }, 'YK-PAE-M06'), {
			productCode: 'YK-PAE-M06',
			notices: [],
			exampleQuestion: null
		});
	});
});

describe('showsPanel', () => {
	it('shows the panel to a visitor (invitation to log in), whatever the coverage', () => {
		assert.equal(showsPanel(false, null), true);
	});

	it('shows the panel to a logged-in customer only when the product has a notice', () => {
		assert.equal(showsPanel(true, { productCode: 'YK-PAE-M08', notices: ['Notice Evolys'], exampleQuestion: null }), true);
		assert.equal(showsPanel(true, { productCode: 'SRK25ZS-W', notices: [], exampleQuestion: null }), false);
		assert.equal(showsPanel(true, null), false);
	});
});

describe('sourceLabel', () => {
	it('reads "Source : <notice>, page <N>"', () => {
		assert.equal(sourceLabel({ source: "Notice d'installation Evolys 8 kW (FICTIF)", page: 12 }), "Source : Notice d'installation Evolys 8 kW (FICTIF), page 12");
	});
});
