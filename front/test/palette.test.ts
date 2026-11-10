// Tests of the palette of the builder (E6): search, rubriques and their order (src/lib/editor/palette.ts),
// the gestures of a block through the Blocks API (stub editor), the thumbnails (src/lib/editor/thumbs/), and
// the palette of the registry (loaded through Vite, like the app). `npm test`.
import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer, type ViteDevServer } from 'vite';
import {
	CATEGORY_ORDER,
	categoryId,
	initiallyOpen,
	insertPaletteBlock,
	LAYOUT_CATEGORY,
	LAYOUT_ORDER,
	matchesSearch,
	normalizeSearch,
	orderPalette,
	paletteGroups,
	startPaletteDrag,
	toggled,
	type PaletteEditor,
	type PaletteEntry
} from '../src/lib/editor/palette.ts';
import { blockThumb, THUMBS } from '../src/lib/editor/thumbs/thumbs.ts';

const entry = (type: string, label: string, category: string): PaletteEntry => ({ type, label, category });

describe('search of the palette', () => {
	it('ignores case, accents, typographic apostrophes and extra spaces', () => {
		assert.equal(normalizeSearch('  Catégories   PHARES '), 'categories phares');
		assert.equal(normalizeSearch('Bandeau d’appel à l’action'), "bandeau d'appel a l'action");
		assert.ok(matchesSearch('Catégories phares', 'categorie'));
		assert.ok(matchesSearch('Bandeau d\'appel à l\'action', 'D’APPEL'));
		assert.ok(matchesSearch('Texte + image', 'IMAGE'));
	});

	it('wants every word, in any order; an empty search matches everything', () => {
		assert.ok(matchesSearch('Cartes d\'accompagnement', 'accompagnement cartes'));
		assert.ok(!matchesSearch('Cartes d\'accompagnement', 'cartes produit'));
		assert.ok(matchesSearch('FAQ', ''));
		assert.ok(matchesSearch('FAQ', '   '));
	});
});

describe('rubriques of the palette', () => {
	const registryOrder = [
		entry('hero', 'Bandeau principal', 'Blocs Yack'),
		entry('texte-image', 'Texte + image', LAYOUT_CATEGORY),
		entry('faq', 'FAQ', 'Blocs Yack'),
		entry('produit-galerie', 'Galerie produit', 'Blocs produit'),
		entry('colonnes', 'Colonnes', LAYOUT_CATEGORY),
		entry('section', 'Section', LAYOUT_CATEGORY),
		entry('espace', 'Espace', LAYOUT_CATEGORY),
		entry('titre', 'Titre', LAYOUT_CATEGORY)
	];

	it('puts « Mise en page » first, basic blocks before the containers, then the bound and the Yack blocks', () => {
		assert.equal(CATEGORY_ORDER[0], LAYOUT_CATEGORY);
		assert.deepEqual(
			orderPalette(registryOrder).map((item) => item.type),
			['titre', 'espace', 'texte-image', 'section', 'colonnes', 'produit-galerie', 'hero', 'faq']
		);
	});

	it('keeps the order of the basic blocks to come: Titre, Texte, Bouton, Espace, Texte + image, Image, then the containers', () => {
		assert.deepEqual([...LAYOUT_ORDER], ['titre', 'texte', 'bouton', 'espace', 'texte-image', 'image', 'section', 'colonnes']);
	});

	it('opens on « Mise en page » only', () => {
		const groups = paletteGroups(orderPalette(registryOrder), '', initiallyOpen());
		assert.deepEqual(
			groups.map((group) => [group.category, group.open, group.items.length]),
			[
				[LAYOUT_CATEGORY, true, 5],
				['Blocs produit', false, 1],
				['Blocs Yack', false, 2]
			]
		);
	});

	it('shows, open, only the rubriques holding a match of the search', () => {
		const groups = paletteGroups(orderPalette(registryOrder), 'e', initiallyOpen());
		assert.ok(groups.every((group) => group.open));
		const found = paletteGroups(orderPalette(registryOrder), 'galerie', initiallyOpen());
		assert.deepEqual(
			found.map((group) => [group.category, group.open, group.items.map((item) => item.type)]),
			[['Blocs produit', true, ['produit-galerie']]]
		);
		assert.deepEqual(paletteGroups(registryOrder, 'aucun bloc', initiallyOpen()), []);
	});

	it('lets a rubrique be folded during a search, and remembers the folding outside it', () => {
		const ordered = orderPalette(registryOrder);
		const folded = paletteGroups(ordered, 'a', initiallyOpen(), ['Blocs Yack']);
		assert.equal(folded.find((group) => group.category === 'Blocs Yack')?.open, false);
		const opened = toggled(toggled(initiallyOpen(), 'Blocs Yack'), LAYOUT_CATEGORY);
		assert.deepEqual(opened, ['Blocs Yack']);
		assert.deepEqual(
			paletteGroups(ordered, '', opened).map((group) => group.open),
			[false, false, true]
		);
	});

	it('names each rubrique for aria-controls', () => {
		assert.equal(categoryId('Mise en page'), 'palette-mise-en-page');
		assert.equal(categoryId('Blocs catégorie'), 'palette-blocs-categorie');
	});
});

describe('gestures of a block of the palette', () => {
	/** Stub of the Blocks API: the blocks of the palette by id, and what the gestures did. */
	function stubEditor() {
		const calls: unknown[][] = [];
		const block = {
			get: (name: string) => (name === 'onClick' ? (b: unknown, ed: unknown, options: unknown) => calls.push(['onClick', b, ed, options]) : undefined)
		};
		const editor = {
			Blocks: {
				get: (id: string) => (id === 'faq' ? block : undefined),
				startDrag: (b: unknown, event: unknown) => calls.push(['startDrag', b, event]),
				endDrag: (cancel: boolean) => calls.push(['endDrag', cancel])
			}
		} as unknown as PaletteEditor;
		return { editor, block, calls };
	}

	function dragEvent(dropEffect = 'copy') {
		const data: string[] = [];
		let prevented = false;
		const event = {
			dataTransfer: { setData: (type: string) => data.push(type), effectAllowed: 'all', dropEffect },
			preventDefault: () => (prevented = true)
		};
		return { event: event as unknown as DragEvent, data, prevented: () => prevented };
	}

	it('click: the GrapesJS block does what it does (added at the end of the page)', () => {
		const { editor, block, calls } = stubEditor();
		assert.equal(insertPaletteBlock(editor, 'faq'), true);
		assert.deepEqual(calls, [['onClick', block, editor, {}]]);
		assert.equal(insertPaletteBlock(editor, 'inconnu'), false);
	});

	it('drag: started by the Blocks API, ended by the dragend (cancelled when nothing was dropped)', () => {
		const { editor, block, calls } = stubEditor();
		const start = dragEvent();
		const end = startPaletteDrag(editor, 'faq', start.event);
		assert.ok(end);
		assert.deepEqual(start.data, ['text/plain']);
		assert.equal((start.event.dataTransfer as DataTransfer).effectAllowed, 'copy');
		end(dragEvent('copy').event);
		end(dragEvent('none').event);
		assert.deepEqual(calls, [['startDrag', block, start.event], ['endDrag', false], ['endDrag', true]]);
	});

	it('drag of a block absent from the editor: cancelled', () => {
		const { editor, calls } = stubEditor();
		const start = dragEvent();
		assert.equal(startPaletteDrag(editor, 'inconnu', start.event), null);
		assert.ok(start.prevented());
		assert.deepEqual(calls, []);
	});
});

describe('thumbnails of the palette', () => {
	it('are 64 x 40 diagrams in two greys of the text colour, without colour of their own', () => {
		for (const [type, svg] of Object.entries(THUMBS)) {
			assert.match(svg, /^<svg viewBox="0 0 64 40" width="64" height="40" fill="currentColor" aria-hidden="true" focusable="false">/, type);
			assert.doesNotMatch(svg, /#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|var\(/i, type);
			const opacities = new Set([...svg.matchAll(/fill-opacity="([^"]+)"/g)].map((match) => match[1]));
			assert.ok(opacities.size >= 2 && opacities.size <= 3, `${type} : ${[...opacities].join(', ')}`);
			assert.doesNotMatch(svg, /NaN|undefined/, type);
		}
	});

	it('exist for the basic blocks to come (B8-6)', () => {
		for (const type of LAYOUT_ORDER) assert.ok(blockThumb(type), type);
		assert.equal(blockThumb('constructor'), undefined);
	});
});

describe('palette of the registry', () => {
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

	it('page: « Mise en page » (basic blocks, Texte + image, Section, Colonnes) then « Blocs Yack »', () => {
		const blocks = registry.paletteBlocks(null);
		assert.deepEqual(
			blocks.filter((block) => block.category === LAYOUT_CATEGORY).map((block) => block.type),
			['titre', 'texte', 'bouton', 'espace', 'texte-image', 'section', 'colonnes']
		);
		assert.deepEqual([...new Set(blocks.map((block) => block.category))], [LAYOUT_CATEGORY, 'Blocs Yack']);
		assert.equal(blocks.length, registry.allowedBlocks(null).length);
	});

	it('template: its bound blocks after « Mise en page », without the mandatory block', () => {
		for (const [type, category, mandatory] of [
			['product', 'Blocs produit', 'produit-achat'],
			['taxon', 'Blocs catégorie', 'categorie-produits']
		] as const) {
			const blocks = registry.paletteBlocks(type);
			assert.deepEqual([...new Set(blocks.map((block) => block.category))], [LAYOUT_CATEGORY, category, 'Blocs Yack'], type);
			assert.ok(!blocks.some((block) => block.type === mandatory), type);
		}
	});

	it('puts a basic block of LAYOUT_ORDER in « Mise en page » once it is declared (B8-6)', () => {
		const texte = registry.blockDefinition('texte-image');
		assert.ok(texte);
		for (const type of ['titre', 'texte', 'bouton', 'espace', 'image']) {
			assert.equal(registry.paletteCategory({ ...texte, type: type as never }), LAYOUT_CATEGORY, type);
		}
		assert.equal(registry.paletteCategory({ ...texte, type: 'faq' }), 'Blocs Yack');
	});

	it('every block has its thumbnail', () => {
		assert.deepEqual(
			registry.REGISTRY.filter((block) => blockThumb(block.type) === undefined).map((block) => block.type),
			[]
		);
	});
});
