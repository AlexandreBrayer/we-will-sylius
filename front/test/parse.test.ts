// Tests of the pure block helpers (src/lib/blocks/parse.ts), run by node itself: `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { MAX_LINK_LENGTH, assetUrl, documentLink, isAllowedLink, isVisibleFor, parseCodes, parseFaq, parseLinks, parseParagraphs, resolveLink } from '../src/lib/blocks/parse.ts';

const root = new URL('../../', import.meta.url);
/** Cases of tests/Yack/Content/Domain/BlockLinkTest.php (BlockLink::isAllowed()), read from its file. */
const LINK_CASES = JSON.parse(readFileSync(new URL('tests/Yack/Content/Domain/block-links.json', root), 'utf8')) as Record<'allowed' | 'refused', Record<string, string>>;

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

	it('keeps a link only when Sylius allows it: no tab or control character that leaves the site', () => {
		for (const link of ['/\t/evil.example', '/\t\\evil.example', '/\r/evil.example', '/\u0001/evil.example', '/\u200b/evil.example', 'tel:abc', '/a&colon;b', 'java\tscript:alert(1)']) {
			assert.deepEqual(parseLinks(`Témoin | /contact\nPiège | ${link}`), [{ label: 'Témoin', url: '/contact' }], JSON.stringify(link));
			assert.equal(documentLink(link), '', JSON.stringify(link));
		}
		// Blanks around the link are not part of it (parseFaq trims the column, BlockLink::inLines too).
		assert.deepEqual(parseLinks('Fiche |  /fiche.pdf \u00a0'), [{ label: 'Fiche', url: '/fiche.pdf' }]);
		assert.equal(documentLink(' page:nos-services '), '/nos-services');
		assert.equal(documentLink('tel:+596 596 00 00 00'), 'tel:+596 596 00 00 00');
	});
});

describe('isAllowedLink (same rule and same cases as BlockLink::isAllowed of Sylius)', () => {
	it('accepts every allowed case of BlockLinkTest', () => {
		assert.ok(Object.keys(LINK_CASES.allowed).length >= 15);
		for (const [name, link] of Object.entries(LINK_CASES.allowed)) assert.equal(isAllowedLink(link), true, name);
		assert.equal(isAllowedLink(`/${'a'.repeat(MAX_LINK_LENGTH - 1)}`), true, 'longest link');
		assert.equal(isAllowedLink(`/${'é'.repeat(MAX_LINK_LENGTH - 1)}`), true, 'longest link, counted in characters as mb_strlen');
	});

	it('refuses every refused case of BlockLinkTest', () => {
		assert.ok(Object.keys(LINK_CASES.refused).length >= 45);
		for (const [name, link] of Object.entries(LINK_CASES.refused)) assert.equal(isAllowedLink(link), false, name);
		assert.equal(isAllowedLink(`/${'a'.repeat(MAX_LINK_LENGTH)}`), false, 'too long');
		assert.equal(isAllowedLink('/sav\ud800'), false, 'lone surrogate (invalid UTF-8 for PHP)');
	});

	it('has the same length bound as BlockLink::MAX_LENGTH', () => {
		const php = readFileSync(new URL('src/Yack/Content/Domain/BlockLink.php', root), 'utf8');
		assert.equal(MAX_LINK_LENGTH, Number(/public const MAX_LENGTH = (\d+);/.exec(php)?.[1]));
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

describe('parseParagraphs', () => {
	it('splits on blank lines, keeps single line breaks and drops empty paragraphs', () => {
		assert.deepEqual(parseParagraphs('Un\ndeux\n\nTrois'), ['Un\ndeux', 'Trois']);
		assert.deepEqual(parseParagraphs('Un\r\n\r\nDeux\n \t \nTrois'), ['Un', 'Deux', 'Trois']);
		assert.deepEqual(parseParagraphs('\n\nUn\n\n\n\nDeux\n'), ['Un', 'Deux']);
		assert.deepEqual(parseParagraphs('  retrait gardé\nfin  '), ['  retrait gardé\nfin  ']);
		assert.deepEqual(parseParagraphs(''), []);
		assert.deepEqual(parseParagraphs(undefined), []);
		assert.deepEqual(parseParagraphs(' \n \n'), []);
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
