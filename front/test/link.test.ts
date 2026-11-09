// Tests of the link field (src/lib/editor/traits/link.ts) and of the link props of the registry
// (loaded through Vite, like the app). `npm test`.
// Every link of the CMS fixtures (config/yack/fixtures_cms.yaml) is read with a readable label and
// written back as is; titles and category names come from the fixtures, read as files.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer, type ViteDevServer } from 'vite';
import { resolveLink } from '../src/lib/blocks/parse.ts';
import {
	categoryEntries,
	checkLinkInput,
	describeLink,
	linkNeeds,
	looksLikeAddress,
	matchPages,
	matchShortcuts,
	SHORTCUTS,
	type LinkNames
} from '../src/lib/editor/traits/link.ts';
import type { TaxonNode } from '../src/lib/types.ts';

const root = new URL('../../', import.meta.url);
const CMS = readFileSync(new URL('config/yack/fixtures_cms.yaml', root), 'utf8');
const SHOP = readFileSync(new URL('config/yack/fixtures.yaml', root), 'utf8');

const unquote = (value: string) => value.replace(/^'(.*)'$/, '$1').replace(/''/g, "'");

/** Pages of the fixtures (key = slug, title). */
function fixturePages(): { slug: string; title: string }[] {
	const pages: { slug: string; title: string }[] = [];
	const lines = CMS.split('\n');
	lines.forEach((line, index) => {
		const key = /^ {28}([a-z0-9-]+):$/.exec(line);
		const title = /^ {32}title: (.+)$/.exec(lines[index + 1] ?? '');
		if (key && title) pages.push({ slug: key[1], title: unquote(title[1]) });
	});
	return pages;
}

/** Catalog tree as catalogTree() builds it (sections /produits and /pieces, URL from the slug). */
function fixtureTree(): TaxonNode[] {
	const taxons = [...SHOP.matchAll(/code: '([^']+)'\s*\n\s*translations:\s*\n\s*fr_FR: \{ name: '((?:[^']|'')*)', slug: '([^']+)'/g)].map(
		([, code, name, slug]) => ({ code, name: name.replace(/''/g, "'"), slug })
	);
	const node = (taxon: { code: string; name: string; slug: string }, base: string, url: string): TaxonNode => ({
		code: taxon.code,
		name: taxon.name,
		description: null,
		slug: taxon.slug,
		url: url + taxon.slug.slice(base.length),
		children: taxons
			.filter((child) => child.slug.startsWith(`${taxon.slug}/`) && !child.slug.slice(taxon.slug.length + 1).includes('/'))
			.map((child) => node(child, base, url))
	});
	return [
		{ code: 'clik_yack', url: '/produits' },
		{ code: 'clik_yack_parts', url: '/pieces' }
	].map(({ code, url }) => {
		const section = taxons.find((taxon) => taxon.code === code);
		assert.ok(section, `section ${code} in fixtures.yaml`);
		return node(section, section.slug, url);
	});
}

/** Link values of the fixtures: link props of every block, and the « Libellé | lien » lines of the documents blocks. */
function fixtureLinks(): { prop: string; type: string; value: string }[] {
	const links: { prop: string; type: string; value: string }[] = [];
	let type = '';
	let inItems = false;
	for (const line of CMS.split('\n')) {
		const block = /^\s+-\s+type: ([a-z-]+)$/.exec(line);
		if (block) {
			type = block[1];
			inItems = false;
			continue;
		}
		if (inItems) {
			const item = /^ {48}(.+)$/.exec(line);
			if (item) {
				links.push({ prop: 'items', type, value: item[1].split('|').slice(1).join('|').trim() });
				continue;
			}
			inItems = false;
		}
		const prop = /^ {44}(cta2?Url|url\d+): (.*)$/.exec(line);
		if (prop) links.push({ prop: prop[1], type, value: unquote(prop[2]) });
		if (type === 'documents' && /^ {44}items: \|$/.test(line)) inItems = true;
	}
	return links;
}

const PAGES = fixturePages();
const TREE = fixtureTree();
const NAMES: LinkNames = {
	pages: new Map(PAGES.map((page) => [page.slug, page.title])),
	categories: new Map(categoryEntries(TREE).map((entry) => [entry.url, entry.path])),
	products: new Map([['pac-air-eau-8kw', 'PAC air/eau 8 kW (FICTIF)']])
};

describe('checkLinkInput (same rule as resolveLink of the shop)', () => {
	it('accepts http, https, mailto, tel, a path of the site and page:<slug>, as typed', () => {
		for (const value of ['https://www.yack.fr/catalogue?x=1#a', 'http://example.org', 'mailto:contact@example.org', 'tel:+33596000000', '/sav', '/produits?marque=Yack', '/', 'page:nos-services', 'HTTPS://EXAMPLE.ORG']) {
			assert.deepEqual(checkLinkInput(value), { ok: true, value }, value);
			assert.notEqual(resolveLink(value), '', value);
		}
		assert.deepEqual(checkLinkInput('  /contact  '), { ok: true, value: '/contact' });
	});

	it('refuses javascript:, data: and any other scheme, the protocol-relative URLs and the incomplete ones', () => {
		for (const value of [
			'javascript:alert(1)',
			'JavaScript:alert(1)',
			' javascript:alert(1)',
			'data:text/html,<script>alert(1)</script>',
			'vbscript:msgbox',
			'file:///etc/passwd',
			'ftp://example.org',
			'//evil.example',
			'/\\evil.example',
			'page:Nos Services',
			'page:',
			'https://',
			'https:///evil.example',
			'https://\\evil.example',
			'mailto:',
			'tel:',
			'nos-services'
		]) {
			const result = checkLinkInput(value);
			assert.equal(result.ok, false, value);
		}
		assert.match((checkLinkInput('javascript:alert(1)') as { message: string }).message, /^Adresse refusée : un lien commence par https:\/\//);
		assert.equal((checkLinkInput('www.yack.fr') as { message: string }).message, "Ajoutez https:// devant l'adresse : https://www.yack.fr");
		assert.equal((checkLinkInput('/sav pieces') as { message: string }).message, "Une adresse ne contient pas d'espace.");
		assert.equal((checkLinkInput('java\tscript:alert(1)') as { message: string }).message, "Une adresse ne contient pas d'espace.");
		assert.equal((checkLinkInput('') as { message: string }).message, 'Saisissez une adresse.');
		assert.equal(checkLinkInput(`/${'a'.repeat(2000)}`).ok, false);
	});

	it('tells an address from a search', () => {
		for (const value of ['/sav', 'https://x', 'mailto:a', 'javascript:x', 'www.yack.fr', 'page:x']) assert.ok(looksLikeAddress(value), value);
		for (const value of ['nos services', 'pompe', 'SRK25', 'eau chaude']) assert.ok(!looksLikeAddress(value), value);
	});
});

describe('describeLink', () => {
	it('gives a readable label for each kind of link', () => {
		const cases: [string, string, string][] = [
			['', 'empty', 'Aucun lien'],
			['page:nos-services', 'page', 'Page : Nos services'],
			['page:inconnue', 'page', 'Page introuvable : inconnue'],
			['/sav', 'shortcut', 'Site : SAV et pièces détachées'],
			['/', 'shortcut', 'Site : Accueil'],
			['/produits?marque=Yack', 'shortcut', 'Site : Catalogue Clik&Yack (?marque=Yack)'],
			['/produits/eau-glacee', 'category', 'Catégorie : Clik&Yack › Eau Glacée'],
			['/produits/pompe-a-chaleur-air-air/residentiel', 'category', 'Catégorie : Clik&Yack › Pompe à Chaleur Air/Air › Résidentiel'],
			['/produits/nulle-part', 'category', 'Catégorie introuvable : /produits/nulle-part'],
			['/produit/pac-air-eau-8kw', 'product', 'Produit : PAC air/eau 8 kW (FICTIF)'],
			['/produit/disparu', 'product', 'Produit introuvable : disparu'],
			['/nos-documentations', 'page', 'Page : Documentation'],
			['/compte/adresses', 'path', 'Adresse du site : /compte/adresses'],
			['https://www.yack.fr', 'web', 'Lien externe : https://www.yack.fr'],
			['mailto:contact@example.org', 'mail', 'E-mail : contact@example.org'],
			['tel:+33596000000', 'phone', 'Téléphone : +33596000000'],
			['javascript:alert(1)', 'invalid', 'Lien refusé par la boutique : javascript:alert(1)']
		];
		for (const [value, kind, label] of cases) {
			const description = describeLink(value, NAMES);
			assert.equal(description.label, label, value);
			assert.equal(description.kind, kind, value);
		}
		assert.equal(describeLink('page:inconnue', NAMES).missing, true);
		assert.equal(describeLink('page:nos-services', NAMES).missing, false);
	});

	it('shows the code without calling it missing while the names are not loaded', () => {
		assert.deepEqual(describeLink('page:nos-services', null), { kind: 'page', label: 'Page : nos-services', missing: false });
		assert.deepEqual(describeLink('/produits/eau-glacee', null), { kind: 'category', label: 'Catégorie : /produits/eau-glacee', missing: false });
		assert.deepEqual(describeLink('/sav', null), { kind: 'shortcut', label: 'Site : SAV et pièces détachées', missing: false });
	});

	it('asks the server only for what a link needs', () => {
		assert.deepEqual(linkNeeds('page:le-groupe'), { page: 'le-groupe' });
		assert.deepEqual(linkNeeds('/nos-documentations'), { page: 'nos-documentations' });
		assert.deepEqual(linkNeeds('/produits/eau-glacee?x=1'), { category: '/produits/eau-glacee' });
		assert.deepEqual(linkNeeds('/produit/pac-air-eau-8kw'), { product: 'pac-air-eau-8kw' });
		for (const value of ['', '/sav', '/produits', 'https://x.fr', 'javascript:x', '//evil']) assert.deepEqual(linkNeeds(value), {}, value);
	});
});

describe('search of the field', () => {
	it('offers the shortcuts of the ticket, found without accents', () => {
		const labels = SHORTCUTS.map((shortcut) => shortcut.label);
		for (const label of ['Accueil', 'SAV et pièces détachées', 'Manuels et notices', 'Trouver un installateur', 'Contact', 'Panier']) assert.ok(labels.includes(label), label);
		assert.deepEqual(
			matchShortcuts('pieces').map((option) => option.value),
			['/sav', '/pieces']
		);
		assert.equal(matchShortcuts('').length, SHORTCUTS.length);
		for (const shortcut of SHORTCUTS) assert.notEqual(resolveLink(shortcut.path), '', shortcut.path);
	});

	it('finds CMS pages by title or slug, stored as page:<slug>, drafts marked', () => {
		const options = matchPages([...PAGES, { slug: 'brouillon-essai', title: 'Brouillon d’essai', status: 'draft' }], 'SERVICES');
		assert.deepEqual(options, [{ value: 'page:nos-services', label: 'Nos services', kind: 'page', detail: '/nos-services' }]);
		assert.match(matchPages([{ slug: 'b', title: 'Brouillon', status: 'draft' }], 'brouillon')[0].detail ?? '', /brouillon : invisible/);
		assert.deepEqual(
			matchPages(PAGES, 'yack technical').map((option) => option.value),
			['page:yack-technical-institute']
		);
		assert.equal(matchPages(PAGES, '').length, 8);
	});
});

describe('links of the CMS fixtures', () => {
	const links = fixtureLinks();

	it('are all read', () => {
		// Every `ctaUrl`, `cta2Url`, `url<n>` line of the file (grep -cE "(cta2?Url|url[0-9]+):" gives 59), and the 4 document lines.
		assert.equal(links.filter((link) => link.prop !== 'items').length, (CMS.match(/(cta2?Url|url\d+):/g) ?? []).length);
		assert.equal(links.filter((link) => link.prop === 'items').length, 4);
		assert.ok(links.some((link) => link.value.startsWith('page:')));
	});

	it('each one gets a readable label, never the bare code nor « introuvable »', () => {
		for (const { prop, type, value } of links) {
			const description = describeLink(value, NAMES);
			const where = `${type}.${prop} = « ${value} » → ${description.label}`;
			assert.equal(description.missing, false, where);
			assert.notEqual(description.kind, 'invalid', where);
			assert.notEqual(description.label, value, where);
			assert.match(description.label, /^(Aucun lien|(Page|Site|Catégorie|Produit|Adresse du site|Lien externe|E-mail|Téléphone) : .+)$/, where);
			if (value.startsWith('page:')) assert.equal(description.label, `Page : ${NAMES.pages.get(value.slice(5))}`, where);
		}
	});

	it('each one is written back exactly: picked in the search, or typed', () => {
		for (const { value } of links) {
			if (value === '') continue;
			// Typed as is: the field stores the same string.
			assert.deepEqual(checkLinkInput(value), { ok: true, value }, value);
			const description = describeLink(value, NAMES);
			if (description.kind === 'page' && value.startsWith('page:')) {
				const title = NAMES.pages.get(value.slice(5)) ?? '';
				assert.ok(matchPages(PAGES, title).some((option) => option.value === value), value);
			}
			if (description.kind === 'shortcut' && !/[?#]/.test(value)) {
				assert.ok(matchShortcuts(description.label.replace(/^Site : /, '')).some((option) => option.value === value), value);
			}
			if (description.kind === 'category') {
				assert.ok(categoryEntries(TREE).some((entry) => entry.url === value), value);
			}
		}
	});
});

describe('registry: link props use the yack-link field', () => {
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

	it('ctaUrl, cta2Url and url<n> are yack-link fields, and nothing else is', () => {
		const links = registry.REGISTRY.flatMap((entry) => entry.traits.filter((trait) => trait.type === 'yack-link').map((trait) => `${entry.type}.${trait.name}`));
		assert.deepEqual(links, [
			'hero.ctaUrl',
			'hero.cta2Url',
			'texte-image.ctaUrl',
			'cta.ctaUrl',
			'acces-rapides.url1',
			'acces-rapides.url2',
			'acces-rapides.url3',
			'acces-rapides.url4',
			'tuiles.url1',
			'tuiles.url2',
			'tuiles.url3',
			'tuiles.url4',
			'tuiles.url5',
			'accompagnement.url1',
			'accompagnement.url2',
			'accompagnement.url3'
		]);
		for (const entry of registry.REGISTRY) {
			for (const trait of entry.traits) {
				if (trait.type === 'yack-link') assert.equal(trait.placeholder, undefined, `${entry.type}.${trait.name}`);
			}
		}
	});

	it('every link prop of the fixtures is a yack-link field of its block', () => {
		for (const { prop, type } of fixtureLinks()) {
			if (prop === 'items') continue;
			const trait = registry.blockDefinition(type)?.traits.find((entry) => entry.name === prop);
			assert.equal(trait?.type, 'yack-link', `${type}.${prop}`);
		}
	});
});
