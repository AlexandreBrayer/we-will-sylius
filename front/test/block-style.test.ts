// Tests of the block style in the builder (src/lib/editor/panels/blockStyle.ts, roving.ts) and of the
// Style / Avancé fields of the registry (loaded through Vite, like the app). `npm test`.
// The labels are checked against Content\Domain\BlockStyle, read as a file.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer, type ViteDevServer } from 'vite';
import { STYLE_PROPERTIES, STYLE_TAGS, STYLE_VERTICAL_ALIGNMENTS } from '../src/lib/blocks/style.ts';
import {
	allowsFreeValues,
	describeFreeValues,
	freeProperties,
	isFreeValue,
	LEVEL_LABELS,
	normalizeAnchor,
	normalizeFreeValue,
	parseBuilderMe,
	pickStyle,
	RESERVED_ANCHORS,
	STYLE_LABELS,
	styleAttribute,
	styleAttributeChanges,
	styleAttributes,
	warnsOnFreeValues,
	type Charte
} from '../src/lib/editor/panels/blockStyle.ts';
import { rovingIndex } from '../src/lib/editor/panels/roving.ts';

const root = new URL('../../', import.meta.url);
const BLOCK_STYLE = readFileSync(new URL('src/Yack/Content/Domain/BlockStyle.php', root), 'utf8');
const BUILDER_LEVEL = readFileSync(new URL('src/Yack/Content/Domain/BuilderLevel.php', root), 'utf8');

/** Charte::toArray() as GET /builder/me sends it (values of Charte.php). */
const CHARTE: Charte = {
	colors: [
		{ key: 'plum', label: 'Prune', value: '#3d1633' },
		{ key: 'teal', label: 'Turquoise', value: '#82cec8' },
		{ key: 'white', label: 'Blanc', value: '#ffffff' }
	],
	titleSizes: [
		{ key: 's', label: 'Petit', value: '24px' },
		{ key: 'm', label: 'Moyen', value: '32px' },
		{ key: 'l', label: 'Grand', value: '40px' },
		{ key: 'xl', label: 'Très grand', value: '56px' }
	],
	spacings: [
		{ key: '0', label: 'Aucun', value: '0px' },
		{ key: 's', label: 'Petit', value: '16px' },
		{ key: 'm', label: 'Moyen', value: '32px' },
		{ key: 'l', label: 'Grand', value: '48px' },
		{ key: 'xl', label: 'Très grand', value: '80px' }
	],
	radii: [{ key: 'petit', label: 'Petit', value: '0.5rem' }]
};

describe('parseBuilderMe', () => {
	it('reads the answer of /builder/me', () => {
		const me = parseBuilderMe({ level: 'libre_avertissement', levelLabel: 'Libre avec avertissement', firstName: 'Admin', charte: CHARTE });
		assert.equal(me.level, 'libre_avertissement');
		assert.equal(me.firstName, 'Admin');
		assert.deepEqual(me.charte, CHARTE);
	});

	it('falls back on the strictest reading when the answer is not the expected one', () => {
		for (const raw of [null, 'x', [], { level: 'admin' }, { level: 'LIBRE' }]) {
			const me = parseBuilderMe(raw);
			assert.equal(me.level, 'charte', JSON.stringify(raw));
			assert.equal(me.levelLabel, 'Charte');
			assert.deepEqual(me.charte.colors, []);
		}
	});

	it('keeps only #rrggbb colours (written into the swatch style)', () => {
		const me = parseBuilderMe({
			level: 'libre',
			charte: { colors: [{ key: 'a', label: 'A', value: 'red;background:url(x)' }, { key: 'b', label: 'B', value: '#ABCDEF' }, { key: 'c', label: 3, value: '#000000' }] }
		});
		assert.deepEqual(me.charte.colors, [{ key: 'b', label: 'B', value: '#ABCDEF' }]);
	});

	it('has the levels and labels of BuilderLevel', () => {
		for (const [level, label] of Object.entries(LEVEL_LABELS)) {
			assert.match(BUILDER_LEVEL, new RegExp(`= '${level}'`), level);
			assert.ok(BUILDER_LEVEL.includes(`'${label}'`), label);
		}
		assert.deepEqual(
			(['charte', 'libre_avertissement', 'libre'] as const).map((level) => [allowsFreeValues(level), warnsOnFreeValues(level)]),
			[
				[false, false],
				[true, true],
				[true, false]
			]
		);
	});
});

describe('style in the component attributes', () => {
	it('round-trips a style: API -> attributes -> saved block', () => {
		const style = { fond: 'plum', texte: '#ff7700', titre: '60px', hautEspace: 'xl', alignement: 'centre', masquerMobile: 'oui', ancre: 'sav' };
		const attributes: Record<string, string> = { title: 'Titre', ...styleAttributes(style) };
		assert.equal(attributes['yb-fond'], 'plum');
		assert.deepEqual(pickStyle(attributes), style);
	});

	it('reads [] (empty PHP array), an absent or invalid style as no style', () => {
		for (const style of [[], undefined, null, 'plum', { inconnu: 'x' }]) assert.deepEqual(styleAttributes(style), {}, JSON.stringify(style));
		assert.deepEqual(pickStyle({ title: 'x', 'yb-fond': '', 'yb-inconnu': 'x', fond: 'plum' }), {});
	});

	it('gives the attribute changes that bring a component to the saved style, or null', () => {
		const attributes = styleAttributes({ fond: '#82cec8', titre: 'm' });
		assert.deepEqual(styleAttributeChanges(attributes, { fond: 'teal', titre: 'm' }), { 'yb-fond': 'teal' });
		assert.deepEqual(styleAttributeChanges(attributes, []), { 'yb-fond': '', 'yb-titre': '' });
		assert.equal(styleAttributeChanges(attributes, { fond: '#82cec8', titre: 'm' }), null);
		assert.equal(styleAttributeChanges({}, undefined), null);
	});
});

describe('normalizeFreeValue (same result as BlockStyle::normalize)', () => {
	it('brings a free value equal to the charte back to its key', () => {
		assert.deepEqual(normalizeFreeValue('color', '#82CEC8', CHARTE), { ok: true, value: 'teal' });
		assert.deepEqual(normalizeFreeValue('color', ' #FFFFFF ', CHARTE), { ok: true, value: 'white' });
		assert.deepEqual(normalizeFreeValue('title', '32', CHARTE), { ok: true, value: 'm' });
		assert.deepEqual(normalizeFreeValue('title', '56px', CHARTE), { ok: true, value: 'xl' });
		assert.deepEqual(normalizeFreeValue('spacing', '0', CHARTE), { ok: true, value: '0' });
		assert.deepEqual(normalizeFreeValue('spacing', '0px', CHARTE), { ok: true, value: '0' });
	});

	it('keeps a free value in its stored format', () => {
		assert.deepEqual(normalizeFreeValue('color', '#FF7700', CHARTE), { ok: true, value: '#ff7700' });
		assert.deepEqual(normalizeFreeValue('title', '14', CHARTE), { ok: true, value: '14px' });
		assert.deepEqual(normalizeFreeValue('title', '96px', CHARTE), { ok: true, value: '96px' });
		assert.deepEqual(normalizeFreeValue('spacing', '200', CHARTE), { ok: true, value: '200px' });
		assert.deepEqual(normalizeFreeValue('spacing', 'xl', CHARTE), { ok: true, value: 'xl' });
	});

	it('refuses what BlockStyle refuses', () => {
		const refused: [Parameters<typeof normalizeFreeValue>[0], string][] = [
			['color', 'red'],
			['color', '#fff'],
			['color', '#ff7700;background:url(x)'],
			['color', ''],
			['title', '13'],
			['title', '97px'],
			['title', '2.5rem'],
			['title', '40.5'],
			['title', '-1'],
			['spacing', '201'],
			['spacing', '1e2'],
			['spacing', '']
		];
		for (const [kind, input] of refused) assert.equal(normalizeFreeValue(kind, input, CHARTE).ok, false, `${kind} ${input}`);
	});
});

describe('free values and marker of the tree', () => {
	it('tells a free value from a key of the charte', () => {
		assert.equal(isFreeValue('fond', 'plum'), false);
		assert.equal(isFreeValue('fond', '#ff7700'), true);
		assert.equal(isFreeValue('titre', 'xl'), false);
		assert.equal(isFreeValue('titre', '60px'), true);
		assert.equal(isFreeValue('basEspace', '0'), false);
		assert.equal(isFreeValue('largeur', 'pleine'), false);
		assert.equal(isFreeValue('fond', ''), false);
		// Lateral spacings: free like the spacing above; title tag and vertical alignment: never.
		assert.equal(isFreeValue('gaucheEspace', '120px'), true);
		assert.equal(isFreeValue('droiteEspace', '8px'), true);
		assert.equal(isFreeValue('gaucheEspace', 'l'), false);
		assert.equal(isFreeValue('balise', 'h1'), false);
		assert.equal(isFreeValue('alignementVertical', 'bas'), false);
		assert.equal(describeFreeValues({ balise: 'h1', gaucheEspace: '120px', alignementVertical: 'bas' }), 'Espace à gauche 120px');
	});

	it('describes the free values of a block', () => {
		const style = { fond: '#ff7700', texte: 'white', titre: '60px', hautEspace: 'm', arrondi: 'aucun' };
		assert.deepEqual(freeProperties(style), ['fond', 'titre']);
		assert.equal(describeFreeValues(style), 'Fond #ff7700, Taille du titre 60px');
		assert.equal(describeFreeValues({ fond: 'plum' }), '');
	});

	it('has the labels of BlockStyle::PROPERTIES', () => {
		for (const property of STYLE_PROPERTIES) assert.ok(BLOCK_STYLE.includes(`'${property}' => ['${STYLE_LABELS[property]}'`), property);
	});
});

describe('normalizeAnchor', () => {
	it('lowers the case and keeps ANCHOR_PATTERN', () => {
		assert.deepEqual(normalizeAnchor(' Nos-Services '), { ok: true, value: 'nos-services' });
		assert.deepEqual(normalizeAnchor(''), { ok: true, value: '' });
		for (const input of ['a b', 'été', 'a"b', 'x'.repeat(41), '#sav']) assert.equal(normalizeAnchor(input).ok, false, input);
	});

	it('refuses an id of the shop layout and the anchor of another block', () => {
		for (const id of RESERVED_ANCHORS) assert.equal(normalizeAnchor(id).ok, false, id);
		assert.equal(normalizeAnchor('faq', ['sav', 'faq']).ok, false);
		assert.equal(normalizeAnchor('faq', ['sav']).ok, true);
	});

	it('reserves the ids that the shop layout really has', () => {
		const layout = [
			...readdirSync(new URL('../src/lib/layout/', import.meta.url))
				.filter((file) => file.endsWith('.svelte'))
				.map((file) => `src/lib/layout/${file}`)
		]
			.map((path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8'))
			.join('\n');
		for (const id of RESERVED_ANCHORS) assert.ok(layout.includes(`id="${id}"`), id);
	});
});

describe('rovingIndex', () => {
	it('moves the focus with the arrows, Home and End, in a loop', () => {
		assert.equal(rovingIndex('ArrowRight', 0, 3), 1);
		assert.equal(rovingIndex('ArrowDown', 2, 3), 0);
		assert.equal(rovingIndex('ArrowLeft', 0, 3), 2);
		assert.equal(rovingIndex('ArrowUp', 1, 3), 0);
		assert.equal(rovingIndex('Home', 2, 3), 0);
		assert.equal(rovingIndex('End', 0, 3), 2);
		assert.equal(rovingIndex('Enter', 1, 3), null);
		assert.equal(rovingIndex('ArrowRight', 0, 0), null);
	});
});

describe('Style and Avancé fields of the registry', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
	});
	after(() => server.close());

	it('edits every property of BlockStyle once, through its attribute, with its label', () => {
		// Title tag and vertical alignment: fields of the blocks that use them only (TITLED_BLOCKS, TWO_COLUMN_BLOCKS).
		const own = ['balise', 'alignementVertical'];
		assert.deepEqual(
			registry.STYLE_TRAITS.map((trait) => trait.styleProperty),
			STYLE_PROPERTIES.filter((property) => !own.includes(property))
		);
		assert.deepEqual(
			[registry.TITLE_TAG_TRAIT, registry.HERO_TITLE_TAG_TRAIT, registry.VERTICAL_ALIGNMENT_TRAIT].map((trait) => trait.styleProperty),
			['balise', 'balise', 'alignementVertical']
		);
		for (const trait of [...registry.STYLE_TRAITS, registry.TITLE_TAG_TRAIT, registry.HERO_TITLE_TAG_TRAIT, registry.VERTICAL_ALIGNMENT_TRAIT]) {
			assert.equal(trait.name, styleAttribute(trait.styleProperty!));
			assert.equal(trait.label, STYLE_LABELS[trait.styleProperty!]);
		}
	});

	it('puts the colours, sizes, spacings, width, alignment and radius in Style, the rest in Avancé', () => {
		const tab = (tabName: string) => registry.STYLE_TRAITS.filter((trait) => trait.tab === tabName).map((trait) => trait.styleProperty);
		assert.deepEqual(tab('style'), ['fond', 'texte', 'accent', 'titre', 'hautEspace', 'basEspace', 'gaucheEspace', 'droiteEspace', 'largeur', 'alignement', 'arrondi']);
		assert.deepEqual(tab('avance'), ['masquerMobile', 'masquerOrdinateur', 'ancre']);
		const audience = registry.REGISTRY.filter((entry) => entry.scope === null).map((entry) => entry.traits.find((trait) => trait.name === 'audience'));
		assert.ok(audience.every((trait) => trait?.tab === 'avance' && trait.label === 'Profil ciblé'));
	});

	it('offers the choices of BlockStyle (WIDTHS, ALIGNMENTS, YES_NO)', () => {
		const ids = (property: string) => registry.STYLE_TRAITS.find((trait) => trait.styleProperty === property)?.options?.map((option) => option.id);
		assert.deepEqual(ids('largeur'), ['contenu', 'large', 'pleine']);
		assert.deepEqual(ids('alignement'), ['gauche', 'centre', 'droite']);
		assert.deepEqual(ids('masquerMobile'), ['non', 'oui']);
		assert.match(BLOCK_STYLE, /WIDTHS = \['contenu', 'large', 'pleine'\]/);
		assert.match(BLOCK_STYLE, /ALIGNMENTS = \['gauche', 'centre', 'droite'\]/);
	});

	it('gives the Balise field to the blocks with a title, Alignement vertical to the two-column blocks, never to a container', () => {
		const styleFields = (type: string) => [
			...(registry.blockDefinition(type)?.traits ?? []).filter((trait) => trait.tab === 'style').map((trait) => trait.styleProperty),
			...registry.styleTraits(type).map((trait) => trait.styleProperty)
		];
		for (const entry of registry.REGISTRY) {
			const fields = styleFields(entry.type);
			const count = (property: string) => fields.filter((field) => field === property).length;
			assert.equal(count('balise'), registry.TITLED_BLOCKS.includes(entry.type) ? 1 : 0, `${entry.type} balise`);
			assert.equal(count('alignementVertical'), registry.TWO_COLUMN_BLOCKS.includes(entry.type) ? 1 : 0, `${entry.type} alignementVertical`);
			assert.equal(count('gaucheEspace'), 1, `${entry.type} gaucheEspace`);
			assert.equal(count('droiteEspace'), 1, `${entry.type} droiteEspace`);
		}
		assert.deepEqual(registry.TITLED_BLOCKS.filter((type) => registry.blockDefinition(type)?.scope !== null), []);
		assert.deepEqual(
			registry.styleTraits('section').map((trait) => trait.styleProperty),
			['fond', 'texte', 'hautEspace', 'basEspace', 'gaucheEspace', 'droiteEspace', 'largeur', 'masquerMobile', 'masquerOrdinateur', 'ancre']
		);
	});

	it('offers the tags and vertical alignments of BlockStyle, H2 and « Au milieu » when not set, the hero keeping its own rule', () => {
		const ids = (trait: { options?: { id: string }[] }) => trait.options?.map((option) => option.id);
		assert.deepEqual(ids(registry.TITLE_TAG_TRAIT), [...STYLE_TAGS]);
		assert.deepEqual(
			registry.TITLE_TAG_TRAIT.options?.map((option) => option.label),
			['Titre H1', 'Titre H2', 'Titre H3', 'Paragraphe']
		);
		assert.equal(registry.TITLE_TAG_TRAIT.defaultId, 'h2');
		assert.deepEqual(ids(registry.HERO_TITLE_TAG_TRAIT), [...STYLE_TAGS]);
		assert.equal(registry.HERO_TITLE_TAG_TRAIT.defaultId, undefined);
		assert.equal(registry.HERO_TITLE_TAG_TRAIT.withDefault, true);
		assert.deepEqual(ids(registry.VERTICAL_ALIGNMENT_TRAIT), [...STYLE_VERTICAL_ALIGNMENTS]);
		assert.equal(registry.VERTICAL_ALIGNMENT_TRAIT.defaultId, 'milieu');
		const lateral = registry.STYLE_TRAITS.filter((trait) => trait.styleProperty === 'gaucheEspace' || trait.styleProperty === 'droiteEspace');
		const above = registry.STYLE_TRAITS.find((trait) => trait.styleProperty === 'hautEspace');
		for (const trait of lateral) assert.deepEqual({ ...trait, name: '', label: '', styleProperty: undefined }, { ...above, name: '', label: '', styleProperty: undefined });
	});

	it('never sends a style attribute as a prop', () => {
		for (const entry of registry.REGISTRY) {
			const props = registry.pickProps(entry.type, { ...entry.defaults, 'yb-fond': 'plum' });
			assert.ok(!('yb-fond' in props), entry.type);
		}
	});
});
