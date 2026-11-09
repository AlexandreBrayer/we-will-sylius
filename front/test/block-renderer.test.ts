// Server rendering of BlockRenderer (svelte/server, through Vite and the SvelteKit plugin like the
// app): a block without style keeps its markup as is, a styled block gets section.yack-cms-block
// around the same markup; layout containers (section, colonnes) render their content inside the
// markup of _cms-layout.scss. Also the reading of template blocks (normalizeBlocks). `npm test`.
import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import type { Component } from 'svelte';
import { createServer, type ViteDevServer } from 'vite';
import type { PageBlock } from '../src/lib/blocks/types.ts';

const front = fileURLToPath(new URL('../', import.meta.url));
let server: ViteDevServer;
let html: (block: PageBlock | Record<string, unknown>, editor?: boolean, returnPath?: string | null) => Promise<string>;
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
	html = async (block, editor = false, returnPath = null) => {
		const context = yackContextMap({ viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor, returnPath });
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

	it('never lets a trapped value into the markup: attribute, declaration or rule breakouts are dropped', async () => {
		const plain = await html(TEXT_IMAGE);
		const traps: unknown[] = [
			{ fond: '#fff;background:url(x)' },
			{ fond: '#ffffff;background:url(x)' },
			{ titre: '12px}' },
			{ hautEspace: '40px;color:red' },
			{ texte: 'teal" onmouseover="alert(1)' },
			{ ancre: 'faq accueil' },
			{ ancre: 'faq"x' },
			{ largeur: 'pleine" onclick="x' },
			{ style: 'color:red', class: 'x' },
			{ fond: null, texte: ['plum'], accent: { value: 'plum' }, titre: 12 },
			{ fond: { fond: 'plum' } },
			{ fond: 'constructor', arrondi: '__proto__' },
			null,
			[],
			['plum'],
			JSON.parse('{"__proto__":{"fond":"plum"}}')
		];
		for (const style of traps) assert.equal(await html({ ...TEXT_IMAGE, style }), plain, JSON.stringify(style));
		const mixed = await html({ ...TEXT_IMAGE, style: { fond: '#fff;background:url(x)', texte: 'teal" onmouseover="x', accent: '#AbCdEf', titre: '12px}' } });
		assert.equal(mixed, `<section class="yack-cms-block yack-cms-block--accent" style="--yb-accent: #abcdef">${plain}</section>`);
	});

	it('marks the wrapper in the builder canvas (hidden blocks stay selectable)', async () => {
		const styled = await html({ ...TEXT_IMAGE, style: { masquerMobile: 'oui' } }, true);
		assert.match(styled, /^<section class="yack-cms-block yack-cms-block--hide-mobile yack-cms-block--editor">/);
	});

	it('always wraps a block in the builder canvas, so that setting a style keeps the markup of the block', async () => {
		const plain = await html(TEXT_IMAGE);
		for (const style of [undefined, [], {}, { fond: 'red' }]) {
			assert.equal(await html({ ...TEXT_IMAGE, style }, true), `<section class="yack-cms-block yack-cms-block--editor">${plain}</section>`, JSON.stringify(style));
		}
		const styled = await html({ ...TEXT_IMAGE, style: { fond: 'plum' } }, true);
		assert.equal(styled, `<section class="yack-cms-block yack-cms-block--fond yack-cms-block--editor" style="--yb-bg: var(--yack-plum)">${plain}</section>`);
	});
});

describe('BlockRenderer, layout containers', () => {
	const FAQ = { type: 'faq', props: { title: 'Q', items: 'A ? | B' } } as const;

	it('renders a section with its container style only, then its content blocks unchanged', async () => {
		const faq = await html(FAQ);
		const section = await html({ type: 'section', props: {}, style: { fond: 'light', hautEspace: 'l', titre: '60px', ancre: 'demo' }, children: [FAQ] });
		assert.equal(
			section,
			'<div class="yack-cms-layout yack-cms-layout--section yack-cms-layout--fond" style="--yl-bg: var(--yack-light); --yl-pad-top: var(--yack-space-l)" id="demo" data-yack-block="section">' +
				faq +
				'</div>'
		);
	});

	it('renders a colonnes as a grid of its ratio, one column per part, missing columns empty', async () => {
		const faq = await html(FAQ);
		const columns = await html({ type: 'colonnes', props: { ratio: '33-33-33', alignement: 'centre', espacement: 'xl' }, columns: [[FAQ], [], [FAQ, FAQ], [FAQ]] });
		assert.equal(
			columns,
			'<div class="yack-cms-layout yack-cms-layout--colonnes" data-yack-block="colonnes"><div class="container">' +
				'<div class="yack-cms-cols yack-cms-cols--33-33-33 yack-cms-cols--align-centre yack-cms-cols--gap-xl">' +
				`<div class="yack-cms-cols__col">${faq}</div><div class="yack-cms-cols__col"></div><div class="yack-cms-cols__col">${faq}${faq}${faq}</div>` +
				'</div></div></div>'
		);
		const fallback = await html({ type: 'colonnes', props: { ratio: 'x', alignement: 'x', espacement: 'x' } });
		assert.match(fallback, /class="yack-cms-cols yack-cms-cols--50-50 yack-cms-cols--align-haut yack-cms-cols--gap-m"><div class="yack-cms-cols__col"><\/div><div class="yack-cms-cols__col"><\/div><\/div>/);
	});

	it('hides a container and all its content from a profile it does not target', async () => {
		assert.equal(await html({ type: 'section', props: { audience: 'pro' }, children: [FAQ] }), '');
		assert.equal(await html({ type: 'colonnes', props: { ratio: '50-50', audience: 'pro' }, columns: [[FAQ], [FAQ]] }), '');
	});

	it('marks an empty container in the builder canvas', async () => {
		assert.equal(await html({ type: 'section', props: {}, children: [] }, true), '<div class="yack-cms-layout yack-cms-layout--section yack-cms-layout--editor" data-yack-block="section"></div>');
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

	it('reads the content of the containers the same way, a section always with children, a colonnes with its columns', () => {
		assert.deepEqual(
			normalizeBlocks([
				{ type: 'section', props: [], style: { fond: 'plum' }, children: [{ type: 'faq', props: {}, style: [] }, { type: 'colonnes', props: { ratio: '33-66' }, columns: [[{ type: 'cta', props: [], style: { fond: 'plum' } }]] }] },
				{ type: 'section', props: {} },
				{ type: 'colonnes', props: { ratio: '33-33-33' }, columns: [] }
			]),
			[
				{ type: 'section', props: {}, style: { fond: 'plum' }, children: [{ type: 'faq', props: {} }, { type: 'colonnes', props: { ratio: '33-66' }, columns: [[{ type: 'cta', props: {}, style: { fond: 'plum' } }], []] }] },
				{ type: 'section', props: {}, children: [] },
				{ type: 'colonnes', props: { ratio: '33-33-33' }, columns: [[], [], []] }
			]
		);
	});
});

// B8-3 : les liens /connexion et /inscription d'un bloc reviennent sur la page affichée (returnPath du contexte).
describe('BlockRenderer, liens de connexion et d’inscription', () => {
	const HERO = {
		type: 'hero',
		props: { title: 'Documentation technique', theme: 'sombre', ctaLabel: 'Me connecter', ctaUrl: '/connexion', cta2Label: 'Créer mon compte pro', cta2Url: '/inscription' }
	};
	const QUICK = {
		type: 'acces-rapides',
		props: { label1: 'Me connecter', url1: '/connexion', label2: 'SAV', url2: '/sav', label3: 'Retour', url3: '//evil.example' }
	};
	const hrefs = (markup: string) => [...markup.matchAll(/href="([^"]*)"/g)].map((match) => match[1]);

	it('ajoute le retour sur la boutique, profil PRO pour l’inscription', async () => {
		assert.deepEqual(hrefs(await html(HERO, false, '/nos-documentations')), [
			'/connexion?redirect=%2Fnos-documentations',
			'/inscription?profil=pro&amp;redirect=%2Fnos-documentations'
		]);
		assert.deepEqual(hrefs(await html(QUICK, false, '/nos-documentations')), ['/connexion?redirect=%2Fnos-documentations', '/sav']);
	});

	it('garde le lien enregistré sans page connue, avec un retour refusé et dans le canevas du builder', async () => {
		for (const [editor, back] of [[false, null], [false, '//evil.example'], [true, '/nos-documentations']] as const) {
			assert.deepEqual(hrefs(await html(HERO, editor, back)), ['/connexion', '/inscription'], `${editor} ${back}`);
		}
	});
});
