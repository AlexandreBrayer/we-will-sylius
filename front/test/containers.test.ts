// Layout containers of the CMS (section, colonnes): constants shared with Sylius (BlockType), walk of the
// tree (count and French path of every node, as PageBlocks and StyleDeviation), columns of a ratio, reading
// of the API and style of a container (layoutStyleToCss). `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	COLUMN_ALIGNMENTS,
	COLUMN_GAPS,
	COLUMN_RATIOS,
	CONTAINER_STYLE,
	FULL_WIDTH_TYPES,
	columnCount,
	columnRefusal,
	columnsOf,
	countNodes,
	flattenNodes,
	isContainerType,
	isFullWidth,
	nodePaths,
	pickContainerStyle,
	readBlocks
} from '../src/lib/blocks/containers.ts';
import { layoutStyleToCss } from '../src/lib/blocks/style.ts';

const BLOCK_TYPE = readFileSync(new URL('../../src/Yack/Content/Domain/BlockType.php', import.meta.url), 'utf8');

/** A `public const NAME = ['a', 'b'];` list of BlockType.php. */
function phpList(name: string): string[] {
	const match = new RegExp(`public const ${name} = \\[([^\\]]*)\\];`).exec(BLOCK_TYPE);
	assert.ok(match, `BlockType::${name}`);
	return [...match[1].matchAll(/'([^']*)'/g)].map(([, value]) => value);
}

const faq = { type: 'faq', props: {} };
const tree = [
	{ type: 'hero', props: {} },
	{
		type: 'section',
		props: {},
		children: [faq, { type: 'colonnes', props: { ratio: '33-66' }, columns: [[faq], [faq, faq]] }]
	},
	{ type: 'colonnes', props: { ratio: '50-50' }, columns: [[], [faq]] }
];

describe('constants of BlockType', () => {
	it('are the ones of Sylius, in the same order', () => {
		assert.deepEqual([...COLUMN_RATIOS], phpList('COLUMN_RATIOS'));
		assert.deepEqual([...COLUMN_ALIGNMENTS], phpList('COLUMN_ALIGNMENTS'));
		assert.deepEqual([...COLUMN_GAPS], phpList('COLUMN_GAPS'));
		assert.deepEqual([...CONTAINER_STYLE], phpList('CONTAINER_STYLE'));
		assert.deepEqual([...FULL_WIDTH_TYPES], phpList('FULL_WIDTH'));
	});

	it('tells the containers and the full-width blocks', () => {
		assert.ok(isContainerType('section') && isContainerType('colonnes'));
		assert.ok(!isContainerType('faq') && !isContainerType(undefined));
		assert.ok(isFullWidth('hero') && isFullWidth('tuiles'));
		assert.ok(!isFullWidth('faq') && !isFullWidth('accompagnement'));
	});
});

describe('walk of the tree', () => {
	it('counts every node, containers included (PageBlocks::count)', () => {
		assert.equal(countNodes(tree), 9);
		assert.equal(countNodes([]), 0);
		assert.deepEqual(
			flattenNodes(tree).map((node) => node.type),
			['hero', 'section', 'faq', 'colonnes', 'faq', 'faq', 'faq', 'colonnes', 'faq']
		);
	});

	it('gives the French path of every node, as StyleDeviation::$where', () => {
		assert.deepEqual(
			nodePaths(tree).map(({ root, where }) => [root, where]),
			[
				[1, 'Bloc n°1'],
				[2, 'Section 2'],
				[2, 'Section 2 › bloc 1'],
				[2, 'Section 2 › colonnes 2'],
				[2, 'Section 2 › colonnes 2 › colonne 1 › bloc 1'],
				[2, 'Section 2 › colonnes 2 › colonne 2 › bloc 1'],
				[2, 'Section 2 › colonnes 2 › colonne 2 › bloc 2'],
				[3, 'Colonnes 3'],
				[3, 'Colonnes 3 › colonne 2 › bloc 1']
			]
		);
	});
});

describe('columns of a ratio', () => {
	it('counts the columns of each ratio, the default one for an unknown ratio', () => {
		assert.deepEqual(COLUMN_RATIOS.map(columnCount), [2, 2, 2, 3]);
		assert.equal(columnCount('25-75'), 2);
		assert.equal(columnCount(undefined), 2);
	});

	it('gives exactly as many columns as the ratio: missing ones empty, extra blocks in the last one', () => {
		assert.deepEqual(columnsOf(undefined, '33-33-33'), [[], [], []]);
		assert.deepEqual(columnsOf([['a'], ['b']], '33-33-33'), [['a'], ['b'], []]);
		assert.deepEqual(columnsOf([['a'], ['b'], ['c', 'd']], '50-50'), [['a'], ['b', 'c', 'd']]);
		const columns = [['a']];
		const out = columnsOf(columns, '66-33');
		out[0].push('x');
		assert.deepEqual(columns, [['a']], 'the given columns are not changed');
	});
});

describe('reading the API', () => {
	it('reads the content of the containers recursively (PHP [] read as {})', () => {
		assert.deepEqual(
			readBlocks([
				{ type: 'section', props: [], style: { fond: 'light' }, children: [{ type: 'faq', props: { title: 'Q', n: 1 }, style: [] }, null] },
				{ type: 'colonnes', props: { ratio: '50-50' }, style: [], columns: [[{ type: 'faq', props: [] }], 'x'] },
				{ type: 'section', props: [] },
				{ props: {} }
			]),
			[
				{ type: 'section', props: {}, style: { fond: 'light' }, children: [{ type: 'faq', props: { title: 'Q' }, style: {} }] },
				{ type: 'colonnes', props: { ratio: '50-50' }, style: {}, columns: [[{ type: 'faq', props: {}, style: {} }], []] },
				{ type: 'section', props: {}, style: {}, children: [] }
			]
		);
		assert.deepEqual(readBlocks({ type: 'faq' }), []);
	});
});

describe('style of a container', () => {
	it('keeps the properties a container accepts only', () => {
		assert.deepEqual(pickContainerStyle({ fond: 'plum', titre: 'xl', arrondi: 'grand', largeur: 'pleine', ancre: 'a' }), { fond: 'plum', largeur: 'pleine', ancre: 'a' });
	});

	it('gives the modifiers and --yl-* properties of _cms-layout.scss, never a value of the block style', () => {
		assert.deepEqual(layoutStyleToCss(undefined), { modifiers: [], vars: {}, style: '', id: null });
		const css = layoutStyleToCss({ fond: 'light', texte: '#123456', hautEspace: 'l', basEspace: '40px', largeur: 'pleine', masquerMobile: 'oui', ancre: 'demo', titre: 'xl', accent: 'plum' });
		assert.deepEqual(css.modifiers, ['fond', 'texte', 'width-pleine', 'hide-mobile']);
		assert.equal(css.style, '--yl-bg: var(--yack-light); --yl-fg: #123456; --yl-pad-top: var(--yack-space-l); --yl-pad-bottom: 40px');
		assert.equal(css.id, 'demo');
		assert.deepEqual(layoutStyleToCss({ fond: 'red;x', ancre: 'a b' }), { modifiers: [], vars: {}, style: '', id: null });
	});
});

describe('drop refused by a column', () => {
	it('names the block and why the column refuses it (message of the cancelled drop)', () => {
		assert.equal(columnRefusal('hero', 'Bandeau principal'), 'Dépôt annulé : « Bandeau principal » est un bloc pleine largeur, il se place dans une section ou dans la page, pas dans une colonne.');
		assert.equal(columnRefusal('section', 'Section'), 'Dépôt annulé : « Section » se place à la racine de la page, jamais dans une colonne.');
		assert.equal(columnRefusal('colonnes', 'Colonnes'), 'Dépôt annulé : « Colonnes » se placent dans la page ou dans une section, jamais dans une colonne.');
		assert.equal(columnRefusal('produit-galerie', 'Galerie produit'), 'Dépôt annulé : « Galerie produit » est lié à la fiche, il reste au niveau de la page, pas dans une colonne.');
	});
});
