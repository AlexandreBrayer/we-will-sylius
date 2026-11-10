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

// B8-4 : balise du titre, espaces à gauche et à droite, alignement vertical.
describe('BlockRenderer, balise, espaces latéraux et alignement vertical', () => {
	const TITLED = [
		TEXT_IMAGE,
		{ type: 'faq', props: { title: 'Questions', items: 'A ? | B' } },
		{ type: 'cta', props: { title: 'Contact', theme: 'turquoise' } },
		{ type: 'categories', props: { title: 'Gammes', taxonCodes: '' } },
		{ type: 'produits', props: { title: 'À la une', productCodes: '' } },
		{ type: 'documents', props: { title: 'Notices', items: 'Notice | /sav' } },
		{ type: 'reassurance', props: { title: 'Pourquoi Yack', title1: 'Stock' } },
		{ type: 'chiffres-cles', props: { title: 'Chiffres', value1: '30', label1: 'ans' } },
		{ type: 'acces-rapides', props: { title: 'Accès', label1: 'SAV', url1: '/sav' } },
		{ type: 'tuiles', props: { title: 'Solutions', label1: 'PAC', url1: '/produits' } },
		{ type: 'accompagnement', props: { title: 'Accompagnement', heading1: 'Groupe', url1: '/le-groupe' } }
	];

	it('renders a block without these properties exactly as before: an h2 title, a centred row', async () => {
		const plain = await html(TEXT_IMAGE);
		assert.match(plain, /<div class="row align-items-center g-5">/);
		assert.match(plain, /<h2 class="mb-3">SAV &amp; pièces<\/h2>/);
		for (const block of TITLED) {
			const markup = await html(block);
			assert.equal((markup.match(/<h2[ >]/g) ?? []).length >= 1, true, block.type);
			assert.doesNotMatch(markup, /<h1|class="[^"]*\bh2\b/, block.type);
			// An invalid value (the API never sends one) or the default tag leave the block as it was, without wrapper.
			for (const style of [{ balise: 'h2' }, { balise: 'h4' }, { balise: 'div' }, { alignementVertical: 'centre' }]) {
				assert.equal(await html({ ...block, style }), markup, `${block.type} ${JSON.stringify(style)}`);
			}
		}
	});

	it('renders the title in the tag of balise, with the look of an h2, without wrapper', async () => {
		const plain = await html(TEXT_IMAGE);
		const h1 = await html({ ...TEXT_IMAGE, style: { balise: 'h1' } });
		assert.equal(h1, plain.replace('<h2 class="mb-3">SAV &amp; pièces</h2>', '<h1 class="mb-3 h2">SAV &amp; pièces</h1>'));
		assert.match(await html({ ...TEXT_IMAGE, style: { balise: 'p' } }), /<p class="mb-3 h2">SAV &amp; pièces<\/p>/);
		for (const block of TITLED) {
			const markup = await html({ ...block, style: { balise: 'h3' } });
			assert.match(markup, /<h3 class="[^"]*\bh2">/, block.type);
		}
		// Same size class whatever the tag: the title size stays the one of `titre`.
		const sized = await html({ ...TEXT_IMAGE, style: { balise: 'h1', titre: 'xl' } });
		assert.match(sized, /^<section class="yack-cms-block yack-cms-block--titre" style="--yb-title-size: var\(--yack-title-xl\)">/);
		assert.match(sized, /<h1 class="mb-3 h2">/);
	});

	it('keeps the h1 of the hero of a page by default, another tag with its look', async () => {
		const hero = { type: 'hero', props: { title: 'Bienvenue', theme: 'magenta' } };
		assert.match(await html(hero), /<h1 class="yack-cms-hero__title">Bienvenue<\/h1>/);
		assert.match(await html({ ...hero, style: { balise: 'h1' } }), /<h1 class="yack-cms-hero__title">Bienvenue<\/h1>/);
		assert.match(await html({ ...hero, style: { balise: 'h2' } }), /<h2 class="yack-cms-hero__title h1">Bienvenue<\/h2>/);
	});

	it('aligns the two columns of texte-image and hero, with the valign class on the wrapper', async () => {
		const plain = await html(TEXT_IMAGE);
		const bottom = await html({ ...TEXT_IMAGE, style: { alignementVertical: 'bas' } });
		assert.equal(
			bottom,
			`<section class="yack-cms-block yack-cms-block--valign-bas">${plain.replace('row align-items-center g-5', 'row align-items-end g-5')}</section>`
		);
		assert.match(await html({ ...TEXT_IMAGE, style: { alignementVertical: 'haut' } }), /<div class="row align-items-start g-5">/);
		assert.match(await html({ ...TEXT_IMAGE, style: { alignementVertical: 'milieu' } }), /<div class="row align-items-center g-5">/);
		assert.match(await html({ type: 'hero', props: { title: 'B', image: 'yack/cms/hero.jpg' }, style: { alignementVertical: 'bas' } }), /<div class="row align-items-end g-5">/);
	});

	it('puts the lateral spacings on the wrapper as --yb-pad-left / --yb-pad-right', async () => {
		const plain = await html(TEXT_IMAGE);
		assert.equal(
			await html({ ...TEXT_IMAGE, style: { gaucheEspace: 'l', droiteEspace: '120px' } }),
			'<section class="yack-cms-block yack-cms-block--pad-left yack-cms-block--pad-right" style="--yb-pad-left: var(--yack-space-l); --yb-pad-right: 120px">' + plain + '</section>'
		);
		assert.equal(await html({ ...TEXT_IMAGE, style: { gaucheEspace: '40px;color:red' } }), plain);
	});

	it('gives the lateral spacings of a section to the section only', async () => {
		const FAQ = { type: 'faq', props: { title: 'Q', items: 'A ? | B' } };
		const faq = await html(FAQ);
		assert.equal(
			await html({ type: 'section', props: {}, style: { gaucheEspace: 'm', droiteEspace: '8px' }, children: [FAQ] }),
			'<div class="yack-cms-layout yack-cms-layout--section" style="--yl-pad-left: var(--yack-space-m); --yl-pad-right: 8px" data-yack-block="section">' + faq + '</div>'
		);
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
