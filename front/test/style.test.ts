// Tests of the block style (src/lib/blocks/style.ts, D6), run by node itself: `npm test`.
// The lists of style.ts are checked against the PHP sources (Content\Domain\Charte, BlockStyle)
// and the SCSS (_tokens.scss, _cms-blocks.scss), read as files: no copy can drift.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	CHARTE_COLORS,
	CHARTE_RADII,
	CHARTE_SPACINGS,
	CHARTE_TITLE_SIZES,
	SPACING_MAX,
	SPACING_MIN,
	STYLE_ALIGNMENTS,
	STYLE_PROPERTIES,
	STYLE_WIDTHS,
	STYLE_YES_NO,
	TITLE_MAX,
	TITLE_MIN,
	readStyle,
	styleToCss
} from '../src/lib/blocks/style.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const CHARTE = read('src/Yack/Content/Domain/Charte.php');
const BLOCK_STYLE = read('src/Yack/Content/Domain/BlockStyle.php');
const TOKENS = read('assets/shop/styles/_tokens.scss');
const CMS = read('assets/shop/styles/_cms-blocks.scss');

/** Body of a PHP `public const NAME = [...];`. */
function phpConst(source: string, name: string): string {
	const match = new RegExp(`public const ${name} = (\\[[\\s\\S]*?\\]|[^;]+);`).exec(source);
	assert.ok(match, `constante ${name} introuvable`);
	return match[1];
}

/** `'key' => ['Label', 'value']` entries of a PHP family constant (Charte::COLORS...). */
function phpFamily(source: string, name: string): [string, string][] {
	return [...phpConst(source, name).matchAll(/'([^']+)' => \['[^']*', '([^']*)'\]/g)].map((m) => [m[1], m[2]]);
}

/** Top-level `$name: value;` of a SCSS file. */
function scssTokens(source: string): Map<string, string> {
	return new Map([...source.matchAll(/^\$([a-z0-9-]+):\s*([^;]+);/gm)].map((m) => [m[1], m[2].trim()]));
}

describe('style.ts follows BlockStyle and Charte (PHP)', () => {
	it('has the same properties, in the same order', () => {
		const properties = [...phpConst(BLOCK_STYLE, 'PROPERTIES').matchAll(/^\s*'([a-zA-Z]+)' => \[/gm)].map((m) => m[1]);
		assert.deepEqual([...STYLE_PROPERTIES], properties);
	});

	it('has the same charte keys', () => {
		assert.deepEqual([...CHARTE_COLORS], phpFamily(CHARTE, 'COLORS').map(([key]) => key));
		assert.deepEqual([...CHARTE_TITLE_SIZES], phpFamily(CHARTE, 'TITLE_SIZES').map(([key]) => key));
		assert.deepEqual([...CHARTE_SPACINGS], phpFamily(CHARTE, 'SPACINGS').map(([key]) => key));
		assert.deepEqual([...CHARTE_RADII], phpFamily(CHARTE, 'RADII').map(([key]) => key));
	});

	it('has the same choices, bounds and patterns', () => {
		const list = (name: string) => [...phpConst(BLOCK_STYLE, name).matchAll(/'([^']+)'/g)].map((m) => m[1]);
		assert.deepEqual([...STYLE_WIDTHS], list('WIDTHS'));
		assert.deepEqual([...STYLE_ALIGNMENTS], list('ALIGNMENTS'));
		assert.deepEqual([...STYLE_YES_NO], list('YES_NO'));
		assert.deepEqual(
			[TITLE_MIN, TITLE_MAX, SPACING_MIN, SPACING_MAX],
			['TITLE_MIN', 'TITLE_MAX', 'SPACING_MIN', 'SPACING_MAX'].map((name) => Number(phpConst(BLOCK_STYLE, name)))
		);
		// PCRE '/…/D' (no trailing newline) = the same JavaScript pattern (JS `$` never accepts one).
		assert.equal(phpConst(BLOCK_STYLE, 'COLOR_PATTERN'), "'/^#[0-9a-fA-F]{6}$/D'");
		assert.equal(phpConst(BLOCK_STYLE, 'PX_PATTERN'), "'/^(0|[1-9]\\d{0,2})px$/D'");
		assert.equal(phpConst(BLOCK_STYLE, 'ANCHOR_PATTERN'), "'/^[a-z0-9-]{1,40}$/D'");
	});
});

describe('the SCSS declares every charte value styleToCss refers to', () => {
	const tokens = scssTokens(TOKENS);

	it('takes title sizes and spacings from tokens with the values of Charte', () => {
		for (const [key, value] of phpFamily(CHARTE, 'TITLE_SIZES')) assert.equal(tokens.get(`yack-title-${key}`), value, `$yack-title-${key}`);
		for (const [key, value] of phpFamily(CHARTE, 'SPACINGS')) assert.equal(tokens.get(`yack-space-${key}`), value, `$yack-space-${key}`);
	});

	it('declares --yack-<key> from its token for every colour, title size and spacing', () => {
		const declared = (name: string, token: string) => CMS.includes(`--${name}: #{$${token}};`);
		for (const key of CHARTE_COLORS) {
			assert.ok(tokens.has(`yack-${key}`), `token $yack-${key}`);
			assert.ok(declared(`yack-${key}`, `yack-${key}`), `--yack-${key}`);
		}
		for (const key of CHARTE_TITLE_SIZES) assert.ok(declared(`yack-title-${key}`, `yack-title-${key}`), `--yack-title-${key}`);
		for (const key of CHARTE_SPACINGS) assert.ok(declared(`yack-space-${key}`, `yack-space-${key}`), `--yack-space-${key}`);
	});

	it('declares --yack-radius-<key> from the token of Charte::RADIUS_TOKENS (aucun: 0)', () => {
		const radiusTokens = new Map([...phpConst(CHARTE, 'RADIUS_TOKENS').matchAll(/'([^']+)' => '([^']+)'/g)].map((m) => [m[1], m[2]]));
		for (const key of CHARTE_RADII) {
			const token = radiusTokens.get(key);
			assert.ok(CMS.includes(token ? `--yack-radius-${key}: #{$${token}};` : `--yack-radius-${key}: 0;`), `--yack-radius-${key}`);
		}
	});

	it('has a rule for every modifier class and reads every --yb-* property', () => {
		const all = styleToCss({
			fond: 'plum',
			texte: 'white',
			accent: 'yellow',
			titre: 'l',
			hautEspace: 's',
			basEspace: 'm',
			arrondi: 'grand',
			alignement: 'centre',
			masquerMobile: 'oui',
			masquerOrdinateur: 'oui'
		});
		assert.ok(all);
		const classes = [...all.class.split(' '), ...STYLE_WIDTHS.map((w) => `yack-cms-block--width-${w}`), ...STYLE_ALIGNMENTS.map((a) => `yack-cms-block--align-${a}`)];
		// `contenu` is the width without style: nothing to override.
		for (const name of classes.filter((c) => c !== 'yack-cms-block--width-contenu')) {
			const scss = name.replace(/^yack-cms-block/, '');
			assert.ok(CMS.includes(`.${name}`) || CMS.includes(`&${scss}`), `règle .${name}`);
		}
		for (const name of Object.keys(all.vars)) assert.ok(CMS.includes(`var(${name}`), `lecture de ${name}`);
	});
});

describe('readStyle', () => {
	it('reads an empty style sent as [] by the API, or an absent one, as {}', () => {
		for (const value of [[], undefined, null, '', 'fond', 42, ['plum']]) assert.deepEqual(readStyle(value), {}, JSON.stringify(value));
	});

	it('keeps the known properties set to a string, drops the others', () => {
		assert.deepEqual(readStyle({ fond: 'plum', inconnu: 'x', texte: 12, accent: '', ancre: 'faq' }), { fond: 'plum', ancre: 'faq' });
	});
});

describe('styleToCss', () => {
	it('gives nothing without style: the block keeps its markup', () => {
		for (const value of [[], {}, undefined, null, { masquerMobile: 'non', masquerOrdinateur: 'non' }]) {
			assert.equal(styleToCss(value), null, JSON.stringify(value));
		}
	});

	it('turns the example of the ticket into classes and charte custom properties', () => {
		assert.deepEqual(styleToCss({ fond: 'plum', texte: 'white', alignement: 'centre', hautEspace: 'xl' }), {
			class: 'yack-cms-block yack-cms-block--fond yack-cms-block--texte yack-cms-block--align-centre',
			vars: { '--yb-bg': 'var(--yack-plum)', '--yb-fg': 'var(--yack-white)', '--yb-pad-top': 'var(--yack-space-xl)' },
			style: '--yb-bg: var(--yack-plum); --yb-fg: var(--yack-white); --yb-pad-top: var(--yack-space-xl)',
			id: null
		});
	});

	it('maps every charte key to its custom property', () => {
		for (const key of CHARTE_COLORS) assert.equal(styleToCss({ accent: key })?.vars['--yb-accent'], `var(--yack-${key})`);
		for (const key of CHARTE_TITLE_SIZES) assert.equal(styleToCss({ titre: key })?.vars['--yb-title-size'], `var(--yack-title-${key})`);
		for (const key of CHARTE_SPACINGS) assert.equal(styleToCss({ basEspace: key })?.vars['--yb-pad-bottom'], `var(--yack-space-${key})`);
		for (const key of CHARTE_RADII) assert.deepEqual(styleToCss({ arrondi: key })?.vars, { '--yb-radius': `var(--yack-radius-${key})` });
	});

	it('passes a free value within its format and bounds as is', () => {
		const css = styleToCss({ fond: '#FF7700', titre: '14px', hautEspace: '0px', basEspace: '200px' });
		assert.deepEqual(css?.vars, { '--yb-bg': '#ff7700', '--yb-title-size': '14px', '--yb-pad-top': '0px', '--yb-pad-bottom': '200px' });
		assert.equal(styleToCss({ titre: '96px' })?.vars['--yb-title-size'], '96px');
	});

	it('ignores any value outside its format, even if the API let it through', () => {
		const refused: Record<string, string[]> = {
			fond: ['red', '#fff', '#ff770', '#ff77000', '#ff7700\n', 'ff7700', 'url(x)', 'var(--x)', '#ff7700; background: url(x)', 'magenta ', 'Magenta'],
			titre: ['13px', '97px', '200px', '012px', '40 px', '40', '2.5rem', '40px;', 'xxl'],
			hautEspace: ['201px', '-1px', '00px', '1em', 'xs', '10px\n'],
			arrondi: ['8px', 'rond', '0'],
			largeur: ['100%', 'Pleine'],
			alignement: ['center', 'justifie'],
			masquerMobile: ['true', '1', 'OUI'],
			ancre: ['Faq', 'faq accueil', 'faq"', '', 'a'.repeat(41), 'faq\n', '<b>']
		};
		for (const [property, values] of Object.entries(refused)) {
			for (const value of values) assert.equal(styleToCss({ [property]: value }), null, `${property}: ${JSON.stringify(value)}`);
		}
	});

	it('keeps the valid values of a style that also has invalid ones', () => {
		assert.deepEqual(styleToCss({ fond: 'red', texte: 'dark', titre: '200px', inconnu: 'x' }), {
			class: 'yack-cms-block yack-cms-block--texte',
			vars: { '--yb-fg': 'var(--yack-dark)' },
			style: '--yb-fg: var(--yack-dark)',
			id: null
		});
	});

	it('writes nothing but --yb-* custom properties in the style attribute', () => {
		const css = styleToCss({ fond: '#a1b2c3', texte: 'white', accent: 'teal', titre: '40px', hautEspace: 'm', basEspace: '12px', arrondi: 'petit' });
		assert.ok(css);
		for (const declaration of css.style.split('; ')) {
			assert.match(declaration, /^--yb-[a-z-]+: (var\(--yack-[a-z0-9-]+\)|#[0-9a-f]{6}|\d{1,3}px)$/, declaration);
		}
	});

	it('turns width, alignment, hiding and anchor into classes and an id', () => {
		assert.deepEqual(styleToCss({ largeur: 'pleine', alignement: 'droite', masquerMobile: 'oui', masquerOrdinateur: 'oui', ancre: 'faq-2' }), {
			class: 'yack-cms-block yack-cms-block--width-pleine yack-cms-block--align-droite yack-cms-block--hide-mobile yack-cms-block--hide-desktop',
			vars: {},
			style: '',
			id: 'faq-2'
		});
	});
});
