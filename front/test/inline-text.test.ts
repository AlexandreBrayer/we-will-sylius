// Texts typed in place in the builder canvas (D8): rules of src/lib/editor/inline/text.ts (plain text,
// length bounded like Sylius, undo steps by word, list elements written back in their format), the
// texts the blocks make editable (each one a text field of the registry), and the shop's HTML, which
// carries no trace of the editing. `npm test`.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import type { Component } from 'svelte';
import { createServer, type ViteDevServer } from 'vite';
import { parseFaq, parseLinks } from '../src/lib/blocks/parse.ts';
import {
	MAX_TEXT_LENGTH,
	clampText,
	cleanText,
	endsWord,
	listFieldText,
	listWithField,
	shownItemIndex,
	textLength,
	typingKind
} from '../src/lib/editor/inline/text.ts';
import { parseList } from '../src/lib/editor/traits/list.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');

describe('plain text typed in place', () => {
	it('is bounded like PageBlocks::MAX_PROP_LENGTH, counted in characters as mb_strlen does', () => {
		assert.equal(MAX_TEXT_LENGTH, Number(/public const MAX_PROP_LENGTH = (\d+);/.exec(read('src/Yack/Content/Domain/PageBlocks.php'))?.[1]));
		assert.equal(textLength('été'), 3);
		assert.equal(textLength('😀a'), 2);
		assert.equal(clampText('abcdef', 3), 'abc');
		assert.equal(clampText('😀😀😀', 2), '😀😀');
		assert.equal(clampText('ab', 5), 'ab');
		assert.equal(clampText('ab', 0), '');
		assert.equal(textLength(clampText('é'.repeat(MAX_TEXT_LENGTH + 5), MAX_TEXT_LENGTH)), MAX_TEXT_LENGTH);
	});

	it('keeps line breaks only for a multiline prop, and no control character', () => {
		assert.equal(cleanText('Ligne 1\r\nLigne 2\rLigne 3', true), 'Ligne 1\nLigne 2\nLigne 3');
		assert.equal(cleanText('Ligne 1\r\nLigne 2', false), 'Ligne 1 Ligne 2');
		assert.equal(cleanText('Col\tonne\u0007\u0000​', false), 'Col onne​');
		assert.equal(cleanText('Prix : 12 €', false), 'Prix : 12 €');
		assert.equal(cleanText('<b>gras</b> & "guillemets"', false), '<b>gras</b> & "guillemets"');
		assert.equal(cleanText('copie� cassée\u0085', true), 'copie cassée');
	});

	it('closes an undo step after a word, not after each letter', () => {
		assert.equal(typingKind('insertText'), 'insert');
		assert.equal(typingKind('insertCompositionText'), 'insert');
		assert.equal(typingKind('deleteContentBackward'), 'delete');
		assert.equal(typingKind('deleteWordBackward'), 'delete');
		assert.equal(typingKind('historyUndo'), 'other');
		for (const letter of ['a', 'é', '1', '-', null]) assert.equal(endsWord(letter), false, String(letter));
		for (const end of [' ', '\n', '.', ',', ';', ':', '!', '?', '…', '’', '»']) assert.equal(endsWord(end), true, end);
	});
});

describe('list elements typed in place (FAQ, documents)', () => {
	const faq = 'Comment voir les prix ? | Réservés aux comptes ECOM.\n\n | réponse sans question\nQuels moyens de paiement ? | Virement ou carte.\n';

	it('finds the element the shop shows at an index (parseFaq, parseLinks)', () => {
		const list = parseList(faq, 2);
		assert.equal(parseFaq(faq)[1]?.question, 'Quels moyens de paiement ?');
		assert.equal(shownItemIndex(list, 'faq', 0), 0);
		assert.equal(shownItemIndex(list, 'faq', 1), 2);
		assert.equal(shownItemIndex(list, 'faq', 2), -1);
		const docs = 'Catalogue | /produits\nPiège | javascript:alert(1)\nServices | page:nos-services';
		assert.deepEqual(parseLinks(docs).map((item) => item.label), ['Catalogue', 'Services']);
		assert.equal(shownItemIndex(parseList(docs, 2), 'links', 1), 2);
	});

	it('writes the element back in its line, the other lines as they were', () => {
		const list = parseList(faq, 2);
		assert.equal(
			listWithField(list, 2, 0, 'Quels modes de paiement ?'),
			'Comment voir les prix ? | Réservés aux comptes ECOM.\n\n | réponse sans question\nQuels modes de paiement ? | Virement ou carte.\n'
		);
		assert.equal(listWithField(list, 0, 1, 'Comptes ECOM seulement.'), 'Comment voir les prix ? | Comptes ECOM seulement.\n\n | réponse sans question\nQuels moyens de paiement ? | Virement ou carte.\n');
		// Emptied while typing then typed again: the element comes back in its place.
		assert.equal(listWithField(list, 0, 0, ''), 'Comment voir les prix ? | Réservés aux comptes ECOM.\n\n | réponse sans question\nQuels moyens de paiement ? | Virement ou carte.\n'.replace('Comment voir les prix ? |', ' |'));
		assert.equal(listWithField(list, 0, 0, 'Prix ?'), faq.replace('Comment voir les prix ?', 'Prix ?'));
	});

	it('takes no « | » nor line break in a column, as the panel cards', () => {
		assert.equal(listFieldText('Question', 'Question | réponse'), 'Question');
		assert.equal(listFieldText('Question', 'Question\nsuite'), 'Question');
		assert.equal(listFieldText('Question', 'Question ?'), 'Question ?');
		assert.equal(listFieldText('A | B', 'A | B.'), 'A | B.');
	});
});

describe('texts editable in the blocks, and the shop HTML', () => {
	let server: ViteDevServer;
	let registry: typeof import('../src/lib/blocks/registry.ts');
	let html: (block: Record<string, unknown>, editing: boolean) => string;
	const blocksDir = new URL('src/lib/blocks/', new URL('../', import.meta.url));

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
		const shop = { viewer: VISITOR, catalog: { products: {}, taxons: {} }, editor: false };
		const attached: string[] = [];
		const editing = { attach: (_el: HTMLElement, target: { prop: string }) => (attached.push(target.prop), () => {}) };
		html = (block, withEditing) => {
			const body = render(BlockRenderer, { props: { block }, context: yackContextMap(shop, withEditing ? editing : undefined) }).body;
			assert.deepEqual(attached, [], 'an attachment never runs in the server rendering');
			return body;
		};
	});

	after(async () => {
		await server?.close();
	});

	it('makes editable only text props of the registry (text, textarea, list)', () => {
		const components = read('front/src/lib/blocks/components.ts');
		let count = 0;
		for (const file of readdirSync(blocksDir).filter((name) => name.endsWith('.svelte'))) {
			const source = readFileSync(new URL(file, blocksDir), 'utf8');
			const calls = [...source.matchAll(/edit\((?:'([A-Za-z0-9]+)'|`([A-Za-z]+)\$\{[^}]+\}`)/g)];
			if (calls.length === 0) continue;
			const name = file.replace('.svelte', '');
			const type = new RegExp(`'?([a-z-]+)'?: ${name},`).exec(components)?.[1];
			assert.ok(type && registry.blockDefinition(type), `${file} : bloc du registre`);
			const traits = registry.blockDefinition(type)!.traits;
			for (const [, prop, slotted] of calls) {
				const names = prop ? [prop] : traits.map((trait) => trait.name).filter((trait) => new RegExp(`^${slotted}\\d+$`).test(trait));
				assert.ok(names.length > 0, `${file} : ${prop ?? slotted}`);
				for (const traitName of names) {
					const trait = traits.find((candidate) => candidate.name === traitName);
					assert.ok(trait && ['text', 'textarea', 'yack-list'].includes(trait.type), `${type}.${traitName} : champ texte (${trait?.type})`);
					count++;
				}
			}
		}
		// CtaButton gets its attachment from the block that shows it (ctaLabel, cta2Label).
		assert.ok(count >= 40, `${count} textes modifiables`);
	});

	it('renders every free block as on the shop: no attribute, no comment of the editing', () => {
		for (const entry of registry.REGISTRY.filter((candidate) => candidate.scope === null)) {
			const block = { type: entry.type, props: { ...entry.defaults, kicker: 'Surtitre', cta2Label: 'Second', cta2Url: '/sav' } };
			const shop = html(block, false);
			assert.equal(html(block, true), shop, entry.type);
			assert.doesNotMatch(shop, /data-yack-edit|contenteditable|tabindex|role="textbox"/, entry.type);
		}
	});
});
