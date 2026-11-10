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
let nodeDetail: typeof import('../src/lib/editor/tree/componentTree.ts').nodeDetail;

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
	({ nodeDetail } = (await server.ssrLoadModule('/src/lib/editor/tree/componentTree.ts')) as typeof import('../src/lib/editor/tree/componentTree.ts'));
	html = (block, editor = false) => {
		const context = yackContextMap({ viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor });
		// Hydration markers (<!--…-->) are not rendered: compared without them.
		return render(BlockRenderer, { props: { block }, context }).body.replace(/<!--[^>]*-->/g, '');
	};
});

after(async () => {
	await server?.close();
});

const BASIC_BLOCKS = ['titre', 'texte', 'bouton', 'espace'];

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

	it('texte: one multiline text (line breaks typed in place too)', () => {
		const texte = registry.blockDefinition('texte');
		assert.deepEqual(
			texte?.traits.filter((trait) => trait.tab === undefined).map((trait) => [trait.name, trait.type]),
			[['texte', 'textarea']]
		);
		assert.match(texte?.defaults.texte ?? '', /\n\n/);
	});

	it('bouton: a label on one line, a link edited by the link field, three looks of the charte', () => {
		const bouton = registry.blockDefinition('bouton');
		assert.deepEqual(
			bouton?.traits.filter((trait) => trait.tab === undefined).map((trait) => [trait.name, trait.type]),
			[
				['libelle', 'text'],
				['url', 'yack-link'],
				['variante', 'select']
			]
		);
		assert.deepEqual(
			bouton?.traits.find((trait) => trait.name === 'variante')?.options?.map((option) => option.id),
			['plein', 'contour', 'lien']
		);
		assert.ok(registry.LINK_PROP.test('url') && !registry.LINK_PROP.test('urlA'));
	});

	it('espace: a height among the steps of the charte (m by default) and an optional fine line', () => {
		const espace = registry.blockDefinition('espace');
		assert.deepEqual(
			espace?.traits.filter((trait) => trait.tab === undefined).map((trait) => [trait.name, trait.options?.map((option) => option.id)]),
			[
				['hauteur', ['s', 'm', 'l', 'xl']],
				['filet', ['non', 'oui']]
			]
		);
		assert.deepEqual(espace?.defaults, { hauteur: 'm', filet: 'non', audience: 'tous' });
	});

	it('the texts of the basic blocks are the ones the canvas edits in place (one line or multiline)', () => {
		const texts = registry.REGISTRY.filter((entry) => BASIC_BLOCKS.includes(entry.type)).flatMap((entry) =>
			entry.traits.filter((trait) => trait.type === 'text' || trait.type === 'textarea').map((trait) => `${entry.type}.${trait.name}:${trait.type}`)
		);
		assert.deepEqual(texts, ['titre.titre:text', 'titre.sousTitre:text', 'texte.texte:textarea', 'bouton.libelle:text']);
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

describe('texte', () => {
	const TEXT = 'Premier paragraphe,\nsur deux lignes.\n\nSecond paragraphe.';

	it('renders one paragraph per run of lines, single line breaks kept (pre-line), blank lines dropped', () => {
		assert.equal(
			html({ type: 'texte', props: { texte: TEXT } }),
			'<div class="yack-cms-texte container" data-yack-block="texte"><p class="yack-cms-text">Premier paragraphe,\nsur deux lignes.</p><p class="yack-cms-text">Second paragraphe.</p></div>'
		);
		assert.equal(
			html({ type: 'texte', props: { texte: '\n\n  \nSeul\r\n \t\n\n\n' } }),
			'<div class="yack-cms-texte container" data-yack-block="texte"><p class="yack-cms-text">Seul</p></div>'
		);
	});

	it('escapes the text: never HTML', () => {
		const body = html({ type: 'texte', props: { texte: '<img src=x onerror=alert(1)>\n\n<a href="javascript:alert(1)">lien</a> & co' } });
		assert.doesNotMatch(body, /<img|<a /);
		assert.match(body, /<p class="yack-cms-text">&lt;img src=x onerror=alert\(1\)><\/p><p class="yack-cms-text">&lt;a href="javascript:alert\(1\)">lien&lt;\/a> &amp; co<\/p>/);
	});

	it('builder canvas: the whole text in one element, as typed, to edit in place; empty, still there', () => {
		assert.equal(
			html({ type: 'texte', props: { texte: TEXT } }, true),
			`<section class="yack-cms-block yack-cms-block--editor"><div class="yack-cms-texte container" data-yack-block="texte"><p class="yack-cms-text">${TEXT}</p></div></section>`
		);
		assert.match(html({ type: 'texte', props: { texte: '' } }, true), /<p class="yack-cms-text"><\/p>/);
	});

	it('renders nothing on the shop when the text is empty or blank', () => {
		assert.equal(html({ type: 'texte', props: { texte: '' } }), '');
		assert.equal(html({ type: 'texte', props: { texte: ' \n\n \n' } }), '');
	});
});

describe('bouton', () => {
	const button = (props: Record<string, string>, style?: Record<string, string>) => ({ type: 'bouton', props: { libelle: 'Voir le catalogue', url: '/produits', variante: 'plein', ...props }, ...(style ? { style } : {}) });

	it('renders a pill of the charte for each look, the link resolved', () => {
		assert.equal(
			html(button({})),
			'<div class="yack-cms-bouton container" data-yack-block="bouton"><a class="btn btn-lg btn-primary" href="/produits">Voir le catalogue</a></div>'
		);
		assert.match(html(button({ variante: 'contour', url: 'page:nos-services' })), /<a class="btn btn-lg btn-outline-primary" href="\/nos-services">/);
		assert.match(html(button({ variante: 'lien', url: 'https://www.example.com/a?b=1' })), /<a class="btn btn-lg btn-ghost" href="https:\/\/www.example.com\/a\?b=1">/);
		assert.match(html(button({ variante: 'fluo' })), /<a class="btn btn-lg btn-primary"/);
	});

	it('is aligned by its style (alignement)', () => {
		assert.match(html(button({}, { alignement: 'centre' })), /^<section class="yack-cms-block yack-cms-block--align-centre"><div class="yack-cms-bouton container"/);
	});

	it('escapes its label', () => {
		const body = html(button({ libelle: '<img src=x onerror=alert(1)> & co' }));
		assert.doesNotMatch(body, /<img/);
		assert.match(body, />&lt;img src=x onerror=alert\(1\)> &amp; co<\/a>/);
	});

	it('renders nothing on the shop for a refused link (javascript:, //host…), no link or no label', () => {
		const cases: Record<string, string>[] = [{ url: 'javascript:alert(1)' }, { url: 'JaVaScRiPt:alert(1)' }, { url: '//evil.example' }, { url: 'data:text/html,x' }, { url: '' }, { libelle: '' }, { libelle: '  ' }];
		for (const props of cases) {
			assert.equal(html(button(props)), '', JSON.stringify(props));
		}
	});

	it('builder canvas: an incomplete button stays shown, dashed, without link, with what it lacks', () => {
		const refused = html(button({ url: 'javascript:alert(1)' }), true);
		assert.doesNotMatch(refused, /href|javascript/);
		assert.match(refused, /<span class="btn btn-lg btn-primary yack-cms-bouton__incomplet">Voir le catalogue<\/span>/);
		assert.match(refused, /<span class="yack-cms-bouton__note">Lien refusé : le bouton n’apparaît pas sur la boutique\.<\/span>/);
		assert.match(html(button({ url: '' }), true), /Lien manquant :/);
		assert.match(html(button({ libelle: '' }), true), /<span class="btn btn-lg btn-primary yack-cms-bouton__incomplet"><\/span>.*Libellé manquant :/s);
		assert.match(html(button({}), true), /<a class="btn btn-lg btn-primary" href="\/produits">Voir le catalogue<\/a><\/div><\/section>$/);
	});
});

describe('espace', () => {
	it('renders an empty space of the height of the charte, decorative', () => {
		assert.equal(
			html({ type: 'espace', props: { hauteur: 'l', filet: 'non' } }),
			'<div class="yack-cms-espace yack-cms-espace--l container" data-yack-block="espace" aria-hidden="true"></div>'
		);
		for (const hauteur of ['s', 'm', 'l', 'xl']) assert.match(html({ type: 'espace', props: { hauteur } }), new RegExp(`yack-cms-espace--${hauteur} `));
	});

	it('draws a fine line when asked', () => {
		assert.match(html({ type: 'espace', props: { hauteur: 'm', filet: 'oui' } }), /aria-hidden="true"><hr class="yack-cms-espace__filet"\/?><\/div>$/);
	});

	it('falls back to the default height for a value outside the charte, never a free height', () => {
		for (const hauteur of ['', '120px', 'xxl', '"><script>']) {
			assert.match(html({ type: 'espace', props: { hauteur } }), /^<div class="yack-cms-espace yack-cms-espace--m container"/, hauteur);
		}
		assert.match(html({ type: 'espace', props: {} }), /yack-cms-espace--m /);
	});

	it('builder canvas: the same space in the wrapper of the canvas (hatched by the stylesheet), even empty', () => {
		assert.equal(
			html({ type: 'espace', props: { hauteur: 's', filet: 'non' } }, true),
			'<section class="yack-cms-block yack-cms-block--editor"><div class="yack-cms-espace yack-cms-espace--s container" data-yack-block="espace" aria-hidden="true"></div></section>'
		);
	});
});

describe('Structure tree', () => {
	it('names a block by its title, the title or the label of a basic block, or the first line of its text', () => {
		assert.equal(nodeDetail({ title: ' Nos services ', text: 'x' }), 'Nos services');
		assert.equal(nodeDetail({ titre: 'Les blocs de base', sousTitre: 'x' }), 'Les blocs de base');
		assert.equal(nodeDetail({ libelle: 'Voir le catalogue', url: '/produits' }), 'Voir le catalogue');
		assert.equal(nodeDetail({ texte: '\n  \nPremière ligne\nSeconde' }), 'Première ligne');
		assert.equal(nodeDetail({ hauteur: 'm', filet: 'oui' }), '');
		assert.equal(nodeDetail({ title: '', items: 'Q | R' }), '');
	});
});
