// Stylesheet of the basic blocks (assets/shop/styles/_cms-blocks.scss, _cms-layout.scss), read as files: the
// rules the shop and the builder canvas share (Espace, first block of a page, « lien » look, accent, hatching
// of the canvas). Measured in a browser by the reports of B8-8; here, what must not drift. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

const read = (path: string): string => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const BLOCKS = read('assets/shop/styles/_cms-blocks.scss');
const LAYOUT = read('assets/shop/styles/_cms-layout.scss');
/** Section of the basic blocks: from its heading to the end of _cms-blocks.scss. */
const BASIC = BLOCKS.slice(BLOCKS.indexOf('// ── Blocs de base'));
/** Rule of the stylesheet whose selector starts with `selector` (up to the closing brace at line start). */
const rule = (source: string, selector: string): string => {
	const start = source.indexOf(selector);
	assert.ok(start >= 0, selector);
	return source.slice(start, source.indexOf('\n}', start) + 2);
};
const withoutComments = (source: string): string => source.replace(/\/\/[^\n]*/g, '');

describe('stylesheet of the basic blocks', () => {
	it('uses no hard colour: tokens of _tokens.scss or the accent of the style only', () => {
		const code = withoutComments(BASIC) + withoutComments(LAYOUT.slice(LAYOUT.indexOf('// Blocs de base')));
		assert.doesNotMatch(code, /#[0-9a-fA-F]{3,8}\b(?!\{)/);
		assert.doesNotMatch(code, /\b(rgba?|hsla?)\(/);
	});

	it('finds the element of a stack after which comes an Espace without :has() inside :has() (a whole rule would drop)', () => {
		const before = BLOCKS.slice(BLOCKS.indexOf('@function yack-cms-before'));
		const body = before.slice(0, before.indexOf('\n}'));
		assert.match(body, /:has\(\+ #\{\$block\}, \+ \.yack-cms-block > #\{\$block\}, \+ \* > \.yack-cms-block--editor > #\{\$block\}\)/);
		assert.equal(body.match(/:has\(/g)?.length, 1);
		// The selector of the item (with :has) is never given to :has(+ …).
		assert.doesNotMatch(LAYOUT, /:has\(\+ #\{\$yack-cms-espace-item\}/);
	});

	it('Espace: one rule at the root of a page and in a stack, the gap of a stack read from a property set by the stacks only', () => {
		const espace = rule(LAYOUT, '#{$yack-cms-before-espace},\n#{$yack-cms-espace-item}:not(:last-child)');
		assert.match(espace, /margin-bottom: calc\(-1 \* var\(--yack-cms-stack-gap, 0px\)\);/);
		const declarations = [...LAYOUT.matchAll(/--yack-cms-stack-gap: ([^;]+);/g)].map((match) => match[1]);
		assert.deepEqual(declarations, ['#{blocks.$yack-cms-rhythm}']);
		const stacks = rule(LAYOUT, '.yack-cms-layout--section,\n.yack-cms-cols__col {\n    .yack-cms-titre');
		assert.match(stacks, /> \* \{\n\s+--yack-cms-stack-gap/);
		// The distances of a title and a text in a stack give way to the Espace that follows them.
		assert.equal(stacks.match(/:not\(:last-child, #\{\$yack-cms-before-espace\}\)/g)?.length, 2);
		assert.match(rule(LAYOUT, ':not([data-yack-block])#{$yack-cms-before-espace}'), /margin-bottom: 0;/);
	});

	it('first block of a page or of the canvas: a title, a text or a button one rhythm below the top', () => {
		const first = rule(BLOCKS, ":is(.yack-cms-page, [data-gjs-type='wrapper']) > :first-child");
		for (const block of ['titre', 'texte', 'bouton']) assert.ok(first.includes(`yack-cms-item('.yack-cms-${block}')`), block);
		assert.doesNotMatch(first, /espace/);
		assert.match(first, /margin-top: \$yack-cms-rhythm;/);
	});

	it('« lien » look: accent of the charte or of the style, underlined on hover and focus, no padding nor border on the sides', () => {
		const link = rule(BASIC, '.btn.yack-cms-bouton__lien');
		assert.match(link, /--bs-btn-padding-x: 0;/);
		assert.match(link, /border-inline-width: 0;/);
		assert.match(link, /--bs-btn-color: var\(--yb-accent, #\{\$yack-magenta\}\);/);
		assert.match(link, /&:hover,\n\s+&:focus-visible \{\n\s+text-decoration: underline;/);
	});

	it('accent of the style: full and outline buttons and the line of the Espace, only under .yack-cms-block--accent', () => {
		const accent = rule(BASIC, '.yack-cms-block--accent {');
		for (const selector of ['.yack-cms-bouton .btn-primary', '.yack-cms-bouton .btn-outline-primary', '.yack-cms-espace__filet']) {
			assert.ok(accent.includes(selector), selector);
		}
		assert.match(accent, /--bs-btn-bg: var\(--yb-accent\);/);
		assert.match(accent, /border-top-color: var\(--yb-accent\);/);
		// Text on the accent: white, or black on a light accent where the browser reads its lightness.
		assert.match(accent, /--yack-cms-on-accent: #\{\$yack-white\};/);
		assert.match(accent, /@supports \(color: oklch\(from red l c h\)\)/);
	});

	it('Texte + image: its row does not climb over the block before it (row-gap instead of the negative top margin)', () => {
		const row = rule(BLOCKS, '.yack-cms-section > .row {');
		assert.match(row, /row-gap: var\(--bs-gutter-y\);/);
		assert.match(row, /margin-top: 0;\n\n\s+> \* \{\n\s+margin-top: 0;/);
	});

	it('hatching of the Espace: in the builder canvas only', () => {
		const hatching = [...BASIC.matchAll(/repeating-linear-gradient/g)];
		assert.equal(hatching.length, 1);
		const editor = rule(BASIC, '.yack-cms-block--editor {');
		assert.ok(editor.includes('repeating-linear-gradient(-45deg, transparent 0 0.375rem, $yack-gray-ds 0.375rem 0.5625rem)'));
		assert.match(editor, /outline: 1px dashed \$yack-gray;/);
	});
});
