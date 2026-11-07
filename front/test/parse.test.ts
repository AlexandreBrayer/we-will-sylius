// Tests of the pure block helpers (src/lib/blocks/parse.ts), run by node itself: `npm test`.
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { assetUrl, isVisibleFor, parseCodes, parseFaq, parseLinks, resolveLink } from '../src/lib/blocks/parse.ts';

describe('resolveLink', () => {
	it('refuses any link that would leave the site or run code', () => {
		for (const link of ['page:/evil.example', '/\\evil.example', '//evil.example', 'javascript:alert(1)', 'data:text/html,x', 'JavaScript:alert(1)']) {
			assert.equal(resolveLink(link), '', link);
		}
	});

	it('turns a CMS page reference into its path', () => {
		assert.equal(resolveLink('page:nos-services'), '/nos-services');
		assert.equal(resolveLink('  page:nos-services  '), '/nos-services');
	});

	it('refuses a CMS page reference that is not a slug', () => {
		for (const link of ['page:', 'page:Nos-Services', 'page:nos--services', 'page:nos services', 'page:-nos']) {
			assert.equal(resolveLink(link), '', link);
		}
	});

	it('keeps a same-site path and the http(s), mailto and tel URLs as they are', () => {
		assert.equal(resolveLink('/sav'), '/sav');
		assert.equal(resolveLink('/produits?marque=yack'), '/produits?marque=yack');
		assert.equal(resolveLink('https://www.example.com/a'), 'https://www.example.com/a');
		assert.equal(resolveLink('HTTP://example.com'), 'HTTP://example.com');
		assert.equal(resolveLink('mailto:contact@yack.test'), 'mailto:contact@yack.test');
		assert.equal(resolveLink('tel:+33100000000'), 'tel:+33100000000');
	});

	it('gives an empty link for a missing or blank value', () => {
		assert.equal(resolveLink(undefined), '');
		assert.equal(resolveLink('   '), '');
		assert.equal(resolveLink('sav'), '');
	});
});

describe('parseLinks', () => {
	it('keeps the "Label | link" lines whose link is allowed', () => {
		assert.deepEqual(parseLinks('Notice | /x.pdf\nNotice | javascript:x'), [{ label: 'Notice', url: '/x.pdf' }]);
	});

	it('drops lines without a label or without a link', () => {
		assert.deepEqual(parseLinks(' | /x.pdf\nSans lien\r\nFiche | page:fiche-technique\n'), [{ label: 'Fiche', url: '/fiche-technique' }]);
		assert.deepEqual(parseLinks(undefined), []);
	});
});

describe('parseFaq', () => {
	it('splits each line on the first "|" and keeps the rest of the answer', () => {
		assert.deepEqual(parseFaq('Question ? | Réponse | suite\r\n\nSeule'), [
			{ question: 'Question ?', answer: 'Réponse | suite' },
			{ question: 'Seule', answer: '' }
		]);
	});

	it('gives no item for an empty value', () => {
		assert.deepEqual(parseFaq(undefined), []);
		assert.deepEqual(parseFaq(''), []);
	});
});

describe('parseCodes', () => {
	it('splits on spaces, commas and semicolons, without duplicates, in order', () => {
		assert.deepEqual(parseCodes('SRK25ZS-WFT, SRF50ZSX-W;SRK25ZS-WFT\n YKP-V4V-EVO'), ['SRK25ZS-WFT', 'SRF50ZSX-W', 'YKP-V4V-EVO']);
	});

	it('gives no code for an empty value', () => {
		assert.deepEqual(parseCodes(undefined), []);
		assert.deepEqual(parseCodes(' ,; '), []);
	});
});

describe('isVisibleFor', () => {
	const cases: [string | undefined, 'visitor' | 'pro' | 'ecom', boolean][] = [
		[undefined, 'visitor', true],
		['tous', 'pro', true],
		['inconnu', 'ecom', true],
		['visiteur', 'visitor', true],
		['visiteur', 'pro', false],
		['pro', 'pro', true],
		['pro', 'ecom', false],
		['ecom', 'ecom', true],
		['ecom', 'visitor', false],
		['connectes', 'pro', true],
		['connectes', 'ecom', true],
		['connectes', 'visitor', false]
	];

	for (const [audience, profile, expected] of cases) {
		it(`${audience ?? '(absent)'} / ${profile} → ${expected}`, () => {
			assert.equal(isVisibleFor(audience, profile), expected);
		});
	}
});

describe('assetUrl', () => {
	it('gives a root-relative path', () => {
		assert.equal(assetUrl('yack/cms/hero.jpg'), '/yack/cms/hero.jpg');
		assert.equal(assetUrl('/yack/cms/hero.jpg'), '/yack/cms/hero.jpg');
	});
});
