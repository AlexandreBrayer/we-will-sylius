// Line breaks of the multiline props (trait `textarea` of the registry: texts, hero subtitle, card texts,
// produit-sav text): every block renders such a prop in an element that the shop stylesheet sets to
// `white-space: pre-line` (assets/shop/styles/_cms-blocks.scss), read by the shop and by the builder canvas
// (same stylesheet). Titles and buttons are one-line props: never in that rule. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import type { Component } from 'svelte';
import { createServer, type ViteDevServer } from 'vite';

const root = new URL('../../', import.meta.url);
const SCSS = readFileSync(new URL('assets/shop/styles/_cms-blocks.scss', root), 'utf8');

/** Selectors of the rules whose only declaration is `white-space: pre-line`. */
function preLineSelectors(): string[] {
	return [...SCSS.matchAll(/(^|\n)([^{}\n/][^{}]*?)\s*\{\s*white-space: pre-line;\s*\}/g)].flatMap((match) =>
		match[2].split(',').map((selector) => selector.trim())
	);
}

const TEXT = 'Première ligne\nDeuxième ligne';

describe('multiline props show their line breaks', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');
	let html: (block: Record<string, unknown>) => string;

	before(async () => {
		server = await createServer({
			root: fileURLToPath(new URL('../', import.meta.url)),
			logLevel: 'error',
			server: { middlewareMode: true, hmr: false, watch: null },
			appType: 'custom'
		});
		registry = (await server.ssrLoadModule('/src/lib/blocks/registry.ts')) as typeof registry;
		const { render } = (await server.ssrLoadModule('svelte/server')) as typeof import('svelte/server');
		const BlockRenderer = (await server.ssrLoadModule('/src/lib/blocks/BlockRenderer.svelte')).default as Component<{ block: unknown }>;
		const { yackContextMap } = (await server.ssrLoadModule('/src/lib/blocks/context.ts')) as typeof import('../src/lib/blocks/context.ts');
		const { VISITOR } = (await server.ssrLoadModule('/src/lib/types.ts')) as typeof import('../src/lib/types.ts');
		// A product in the context: the produit-sav block shows its text only on a product page.
		const shop = { viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor: false, product: {} as never };
		html = (block) => render(BlockRenderer, { props: { block }, context: yackContextMap(shop) }).body;
	});

	after(async () => {
		await server?.close();
	});

	it('the shop stylesheet keeps the line breaks of the text elements only', () => {
		const selectors = preLineSelectors();
		assert.deepEqual(selectors, [
			'.yack-cms-text',
			'.yack-cms-hero__subtitle',
			'.yack-cms-cta__text',
			'.yack-cms-faq p',
			'.yack-cms-support__text',
			'.yack-product-sav__text'
		]);
		for (const selector of selectors) assert.doesNotMatch(selector, /(__|-)(title|heading|label)\b|btn|button|summary|\bh[1-6]\b/, selector);
	});

	it('renders every multiline prop as is in an element of that rule, without spaces around the text', () => {
		const classes = preLineSelectors()
			.filter((selector) => /^\.[a-z_-]+$/.test(selector))
			.map((selector) => selector.slice(1));
		const seen: string[] = [];
		for (const entry of registry.REGISTRY) {
			for (const trait of entry.traits.filter((candidate) => candidate.type === 'textarea' && !/Codes$/.test(candidate.name))) {
				// Card texts show with their card only: the card needs its heading.
				const slot = /(\d+)$/.exec(trait.name)?.[1];
				const props = { ...entry.defaults, ...(slot ? { [`heading${slot}`]: 'Carte' } : {}), [trait.name]: TEXT };
				const body = html({ type: entry.type, props });
				const element = /<([a-z]+) class="([^"]*)"[^>]*>([^<]*)<\/\1>/g;
				const holder = [...body.matchAll(element)].find((match) => match[3].includes('Première ligne'));
				assert.ok(holder, `${entry.type}.${trait.name} : texte rendu`);
				assert.equal(holder[3], TEXT, `${entry.type}.${trait.name} : texte tel quel, retour à la ligne compris`);
				const shown = holder[2].split(/\s+/).filter((name) => classes.includes(name));
				assert.ok(shown.length > 0, `${entry.type}.${trait.name} : <${holder[1]} class="${holder[2]}"> hors de la règle pre-line`);
				seen.push(`${entry.type}.${trait.name}`);
			}
		}
		assert.deepEqual(seen, [
			'hero.subtitle',
			'texte-image.text',
			'cta.text',
			'accompagnement.text1',
			'accompagnement.text2',
			'accompagnement.text3',
			'documents.text',
			'produit-sav.text'
		]);
	});

	it('renders a FAQ answer in a paragraph of the FAQ (.yack-cms-faq p)', () => {
		const body = html({ type: 'faq', props: { title: 'Questions', items: 'Une question ? | Sa réponse.' } });
		assert.match(body, /<div class="yack-cms-faq">.*<p>Sa réponse\.<\/p>/s);
	});
});
