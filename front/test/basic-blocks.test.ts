// Basic blocks of « Mise en page » (titre, texte, bouton, espace): server rendering of BlockRenderer on the shop
// and in the builder canvas (svelte/server, through Vite like block-renderer.test.ts), their fields in the
// registry and their place in the palette. `npm test`.
import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import type { Component } from 'svelte';
import { createServer, type ViteDevServer } from 'vite';
import { blockThumb } from '../src/lib/editor/thumbs/thumbs.ts';

const front = fileURLToPath(new URL('../', import.meta.url));
let server: ViteDevServer;
let registry: typeof import('../src/lib/blocks/registry.ts');
let html: (block: Record<string, unknown>, editor?: boolean) => string;

before(async () => {
	server = await createServer({
		root: front,
		logLevel: 'error',
		server: { middlewareMode: true, hmr: false, watch: null },
		appType: 'custom'
	});
	const { render } = (await server.ssrLoadModule('svelte/server')) as typeof import('svelte/server');
	const BlockRenderer = (await server.ssrLoadModule('/src/lib/blocks/BlockRenderer.svelte')).default as Component<{ block: unknown }>;
	const { yackContextMap } = (await server.ssrLoadModule('/src/lib/blocks/context.ts')) as typeof import('../src/lib/blocks/context.ts');
	const { VISITOR } = (await server.ssrLoadModule('/src/lib/types.ts')) as typeof import('../src/lib/types.ts');
	registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
	html = (block, editor = false) => {
		const context = yackContextMap({ viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor });
		// Hydration markers (<!--…-->) are not rendered: compared without them.
		return render(BlockRenderer, { props: { block }, context }).body.replace(/<!--[^>]*-->/g, '');
	};
});

after(async () => {
	await server?.close();
});

const BASIC_BLOCKS = ['titre'];

describe('basic blocks in the registry', () => {
	it('are free blocks of « Mise en page », first, in the order of the palette, each with its thumbnail', () => {
		const layout = registry
			.paletteBlocks(null)
			.filter((entry) => entry.category === 'Mise en page')
			.map((entry) => entry.type);
		assert.deepEqual(layout.slice(0, BASIC_BLOCKS.length), BASIC_BLOCKS);
		for (const type of BASIC_BLOCKS) {
			const definition = registry.blockDefinition(type);
			assert.equal(definition?.scope, null, type);
			assert.ok(blockThumb(type), type);
			assert.equal(definition?.defaults.audience, 'tous', type);
		}
	});

	it('titre: a title and an optional subtitle on one line, the tag of the title in its style', () => {
		const titre = registry.blockDefinition('titre');
		assert.deepEqual(
			titre?.traits.filter((trait) => trait.tab !== 'avance' && trait.tab !== 'style').map((trait) => [trait.name, trait.type]),
			[
				['titre', 'text'],
				['sousTitre', 'text']
			]
		);
		assert.ok(titre?.traits.some((trait) => trait.styleProperty === 'balise'));
		assert.deepEqual(registry.pickProps('titre', { titre: 'Bonjour', id: 'x', class: 'y' }), { titre: 'Bonjour', sousTitre: '', audience: 'tous' });
	});
});

describe('titre', () => {
	it('renders an h2 with the look of an h2, and the subtitle when there is one', () => {
		assert.equal(
			html({ type: 'titre', props: { titre: 'Nos services', sousTitre: '' } }),
			'<div class="yack-cms-titre container" data-yack-block="titre"><h2 class="yack-cms-titre__titre h2">Nos services</h2></div>'
		);
		assert.match(
			html({ type: 'titre', props: { titre: 'Nos services', sousTitre: 'Avant, pendant et après le chantier' } }),
			/<h2 class="yack-cms-titre__titre h2">Nos services<\/h2><p class="yack-cms-titre__sous-titre lead">Avant, pendant et après le chantier<\/p><\/div>$/
		);
	});

	it('takes the tag of its style (balise), keeps the look of an h2, size and alignment on the wrapper', () => {
		const body = html({ type: 'titre', props: { titre: 'Accueil' }, style: { balise: 'h1', titre: 'xl', alignement: 'centre' } });
		assert.match(body, /^<section class="yack-cms-block yack-cms-block--titre yack-cms-block--align-centre" style="--yb-title-size: var\(--yack-title-xl\)">/);
		assert.match(body, /<h1 class="yack-cms-titre__titre h2">Accueil<\/h1>/);
		assert.match(html({ type: 'titre', props: { titre: 'Note' }, style: { balise: 'p' } }), /<p class="yack-cms-titre__titre h2">Note<\/p>/);
		assert.match(html({ type: 'titre', props: { titre: 'X' }, style: { balise: 'script' } }), /<h2 class="yack-cms-titre__titre h2">X<\/h2>/);
	});

	it('escapes its texts: never HTML', () => {
		const body = html({ type: 'titre', props: { titre: '<script>alert(1)</script>', sousTitre: '<b>gras</b> & co' } });
		assert.doesNotMatch(body, /<script>|<b>/);
		assert.match(body, /&lt;script>alert\(1\)&lt;\/script>/);
		assert.match(body, /&lt;b>gras&lt;\/b> &amp; co/);
	});

	it('renders nothing on the shop when empty, an empty title to type in the builder canvas', () => {
		assert.equal(html({ type: 'titre', props: { titre: '', sousTitre: '' } }), '');
		assert.match(html({ type: 'titre', props: { titre: '', sousTitre: '' } }, true), /<h2 class="yack-cms-titre__titre h2"><\/h2>/);
	});
});
