// Server rendering of BlockRenderer (svelte/server, through Vite and the SvelteKit plugin like the
// app): a block without style keeps its markup as is, a styled block gets section.yack-cms-block
// around the same markup. Also the reading of template blocks (normalizeBlocks). `npm test`.
import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import type { Component } from 'svelte';
import { createServer, type ViteDevServer } from 'vite';
import type { PageBlock } from '../src/lib/blocks/types.ts';

const front = fileURLToPath(new URL('../', import.meta.url));
let server: ViteDevServer;
let html: (block: PageBlock | Record<string, unknown>, editor?: boolean) => Promise<string>;
let normalizeBlocks: typeof import('../src/lib/server/templates.ts').normalizeBlocks;

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
	({ normalizeBlocks } = (await server.ssrLoadModule('/src/lib/server/templates.ts')) as typeof import('../src/lib/server/templates.ts'));
	html = async (block, editor = false) => {
		const context = yackContextMap({ viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor });
		// Hydration markers (<!--…-->) are not rendered: compared without them.
		return render(BlockRenderer, { props: { block }, context }).body.replace(/<!--[^>]*-->/g, '');
	};
});

after(async () => {
	await server?.close();
});

const TEXT_IMAGE = {
	type: 'texte-image',
	props: { title: 'SAV & pièces', text: 'Vues éclatées', image: 'yack/cms/pac-split.png', imagePosition: 'gauche', ctaLabel: 'Accéder au SAV', ctaUrl: '/sav' }
} as const;

describe('BlockRenderer', () => {
	it('renders a block without style, with an empty style ([] from the API) or an invalid one exactly the same', async () => {
		const plain = await html(TEXT_IMAGE);
		assert.match(plain, /^<section class="yack-cms-section container" data-yack-block="texte-image">/);
		for (const style of [[], {}, { fond: 'red', titre: '200px', ancre: 'Faq' }, { masquerMobile: 'non' }]) {
			assert.equal(await html({ ...TEXT_IMAGE, style }), plain, JSON.stringify(style));
		}
	});

	it('wraps a styled block in section.yack-cms-block with classes and --yb-* properties only', async () => {
		const plain = await html(TEXT_IMAGE);
		const styled = await html({ ...TEXT_IMAGE, style: { fond: 'plum', texte: 'white', alignement: 'centre', hautEspace: 'xl', ancre: 'sav' } });
		assert.equal(
			styled,
			'<section class="yack-cms-block yack-cms-block--fond yack-cms-block--texte yack-cms-block--align-centre"' +
				' style="--yb-bg: var(--yack-plum); --yb-fg: var(--yack-white); --yb-pad-top: var(--yack-space-xl)" id="sav">' +
				plain +
				'</section>'
		);
	});

	it('marks the wrapper in the builder canvas (hidden blocks stay selectable)', async () => {
		const styled = await html({ ...TEXT_IMAGE, style: { masquerMobile: 'oui' } }, true);
		assert.match(styled, /^<section class="yack-cms-block yack-cms-block--hide-mobile yack-cms-block--editor">/);
	});
});

describe('normalizeBlocks (templates of the shop and of the builder)', () => {
	it('keeps a set style, drops an empty ([]) or absent one so an unstyled block is unchanged', () => {
		assert.deepEqual(
			normalizeBlocks([
				{ type: 'hero', props: [] },
				{ type: 'cta', props: { title: 'Contact' }, style: [] },
				{ type: 'faq', props: {}, style: { fond: 'plum', inconnu: 'x' } }
			]),
			[
				{ type: 'hero', props: {} },
				{ type: 'cta', props: { title: 'Contact' } },
				{ type: 'faq', props: {}, style: { fond: 'plum' } }
			]
		);
	});
});
