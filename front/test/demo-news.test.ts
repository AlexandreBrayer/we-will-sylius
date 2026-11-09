// Menu « Nouveautés » de la barre de démo (src/lib/layout/demo-news.ts) : groupes, entrées, liens et profils.
// Les liens sont contrôlés contre les routes du front, les pages CMS des fixtures et les routes du back-office. `npm test`.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { describe, it } from 'node:test';
import { DEMO_NEWS, DEMO_NEWS_GROUPS, demoNewsByGroup, demoNewsFor, isBackOffice } from '../src/lib/layout/demo-news.ts';
import { DEMO_PROFILES } from '../src/lib/layout/demo-profiles.ts';

const root = new URL('../../', import.meta.url);
const read = (path: string): string => readFileSync(new URL(path, root), 'utf8');
const SHOP_ROUTES = new URL('front/src/routes/(shop)/', root);

/** Slugs des pages CMS publiées par les fixtures (une page en brouillon ne s'affiche pas sur la boutique). */
function publishedCmsSlugs(): Set<string> {
	const yaml = read('config/yack/fixtures_cms.yaml');
	const pages = [...yaml.matchAll(/\n {28}([a-z0-9-]+):\n {32}title: [^\n]+\n((?: {32}[^\n]*\n)*)/g)];
	return new Set(pages.filter(([, , body]) => !/^ {32}status: draft$/m.test(body)).map(([, slug]) => slug));
}

/** Le chemin (sans requête ni ancre) répond par une route du front, une page CMS des fixtures ou une route du back-office. */
function routeExists(href: string): boolean {
	const path = href.split(/[?#]/)[0];
	if (path === '/admin/') return true; // tableau de bord de Sylius
	if (path.startsWith('/admin/')) {
		const routes = [...read('src/Yack/Content/Infrastructure/Controller/Admin/StyleDeviationController.php').matchAll(/#\[Route\('\/%sylius_admin\.path_name%([^']*)'/g)];
		return routes.some(([, prefix]) => `/admin${prefix}` === path);
	}
	if (path.startsWith('/cms-admin/')) return existsSync(new URL(`front/src/routes${path}/+page.svelte`, root));
	const segments = path.split('/').filter(Boolean);
	if (existsSync(new URL(`${segments.join('/')}/+page.svelte`, SHOP_ROUTES))) return true;
	return segments.length === 1 && publishedCmsSlugs().has(segments[0]);
}

describe('menu Nouveautés de la barre de démo', () => {
	const groupKeys = DEMO_NEWS_GROUPS.map((group) => group.key);
	const profileKeys = DEMO_PROFILES.map((profile) => profile.key);

	it('range les groupes dans l’ordre : devis V1, devis V2, options chiffrées, pistes hors devis', () => {
		assert.deepEqual(groupKeys, ['devis-v1', 'devis-v2', 'options', 'hors-devis']);
		assert.deepEqual(
			DEMO_NEWS_GROUPS.map((group) => group.title),
			['Prévu au devis, V1', 'Prévu au devis, V2', 'Options chiffrées de la proposition', 'Pistes pour la suite, hors devis']
		);
		for (const group of DEMO_NEWS_GROUPS) assert.ok(group.subtitle.length > 0, group.key);
	});

	it('dit que les options et les pistes hors devis ne sont pas incluses dans le prix de base', () => {
		for (const group of DEMO_NEWS_GROUPS.filter((g) => g.key === 'options' || g.key === 'hors-devis')) {
			assert.match(group.subtitle, /non incluses dans le prix de base|ni incluses dans le prix de base/, group.key);
		}
	});

	it('donne à chaque entrée un groupe connu, un libellé et au moins un profil connu', () => {
		for (const item of DEMO_NEWS) {
			assert.ok(groupKeys.includes(item.group), `${item.href} : groupe ${item.group}`);
			assert.ok(item.label.trim().length > 0, item.href);
			assert.ok(item.profiles.length > 0, item.href);
			for (const profile of item.profiles) assert.ok(profileKeys.includes(profile), `${item.href} : profil ${profile}`);
		}
		for (const key of groupKeys) assert.ok(DEMO_NEWS.some((item) => item.group === key), `groupe vide : ${key}`);
	});

	it('n’a aucun lien ni libellé en double', () => {
		const hrefs = DEMO_NEWS.map((item) => item.href);
		assert.equal(new Set(hrefs).size, hrefs.length, hrefs.join(', '));
		const labels = DEMO_NEWS.map((item) => item.label);
		assert.equal(new Set(labels).size, labels.length, labels.join(', '));
	});

	it('ne mène qu’à des écrans qui existent', () => {
		for (const item of DEMO_NEWS) {
			assert.match(item.href, /^\/(?!\/)/, item.href);
			assert.ok(routeExists(item.href), `écran introuvable : ${item.href}`);
		}
	});

	it('ne mène pas au rapport des écarts de charte, absent du menu du back-office', () => {
		// L'écran s'ouvre par son adresse seulement (src/Yack/Content/Infrastructure/Menu/AdminMenuListener.php).
		assert.ok(!DEMO_NEWS.some((item) => item.href.startsWith('/admin/yack/contenu/ecarts-de-charte')));
		assert.ok(!DEMO_NEWS.some((item) => item.label === 'Liberté de style encadrée'));
	});

	it('marque comme back-office les seules entrées du profil admin, et elles seules mènent au back-office', () => {
		for (const item of DEMO_NEWS) {
			const backOfficePath = item.href.startsWith('/admin/') || item.href.startsWith('/cms-admin/');
			assert.equal(isBackOffice(item), backOfficePath, item.href);
			if (isBackOffice(item)) assert.deepEqual(item.profiles, ['admin'], item.href);
		}
	});

	it('montre à chaque profil ses entrées et les écrans du back-office', () => {
		const labels = (profile: 'visiteur' | 'pro' | 'ecom') => demoNewsFor(profile).map((item) => item.label);
		assert.ok(labels('visiteur').includes('Inscription professionnelle'));
		assert.ok(!labels('pro').includes('Inscription professionnelle'));
		const payment = 'Paiement : carte et virement à 15 jours avec escompte, virement à 30 jours';
		assert.ok(labels('ecom').includes(payment));
		assert.ok(!labels('pro').includes(payment));
		assert.ok(!labels('visiteur').includes(payment));
		assert.ok(labels('pro').includes('Demande d’ouverture à la commande'));
		assert.ok(!labels('ecom').includes('Demande d’ouverture à la commande'));
		for (const profile of ['visiteur', 'pro', 'ecom'] as const) {
			const visible = demoNewsFor(profile);
			assert.ok(DEMO_NEWS.filter(isBackOffice).every((item) => visible.includes(item)), profile);
			assert.ok(visible.every((item) => isBackOffice(item) || item.profiles.includes(profile)), profile);
		}
	});

	it('retire les entrées de Manuels et notices quand le module est coupé', () => {
		const on = demoNewsFor('visiteur').map((item) => item.href);
		const off = demoNewsFor('visiteur', { assistance: false }).map((item) => item.href);
		assert.ok(on.includes('/assistance'));
		assert.deepEqual(
			off,
			on.filter((href) => !href.startsWith('/assistance'))
		);
	});

	it('regroupe les entrées dans l’ordre des groupes, sans groupe vide', () => {
		const grouped = demoNewsByGroup(demoNewsFor('pro'));
		assert.deepEqual(
			grouped.map(({ group }) => group.key),
			groupKeys.filter((key) => demoNewsFor('pro').some((item) => item.group === key))
		);
		for (const { group, items } of grouped) assert.ok(items.length > 0 && items.every((item) => item.group === group.key), group.key);
		assert.deepEqual(demoNewsByGroup([{ group: 'options', label: 'x', href: '/x', profiles: ['pro'] }]).map(({ group }) => group.key), ['options']);
	});

	it('nomme les moyens de paiement tels que la configuration les règle', () => {
		// config/packages/yack.yaml : escompte par moyen (carte et virement à 15 jours : 2 %, virement à échéance : 0) et délais.
		const yaml = read('config/packages/yack.yaml');
		const rate = (code: string) => yaml.match(new RegExp(`\\n {8}${code}: (\\d+)\\n`))?.[1];
		const days = (code: string) => yaml.match(new RegExp(`\\n {8}${code}: \\{ kind: 'virement', due_days: (\\d+) \\}`))?.[1];
		assert.deepEqual([rate('carte_bancaire'), rate('virement_15j'), rate('virement_echeance')], ['2', '2', '0']);
		assert.deepEqual([days('virement_15j'), days('virement_echeance')], ['15', '30']);
		const payment = DEMO_NEWS.find((item) => item.href === '/panier');
		assert.equal(payment?.label, 'Paiement : carte et virement à 15 jours avec escompte, virement à 30 jours');
	});

	it("dit quand l'inscription PRO vérifie le SIRET : en quittant l'étape Société, puis à la création", () => {
		// Le SIRET est vérifié par validateStep(2) au clic sur le bouton de l'étape (« Continuer »), puis par l'API à
		// l'envoi final (Domain\Siret) ; jamais pendant la frappe.
		const hint = DEMO_NEWS.find((item) => item.href === '/inscription?profil=pro')?.hint ?? '';
		assert.match(hint, /au clic sur « Continuer », puis à la création du compte/);
		assert.doesNotMatch(hint, /à la saisie/);
		const page = read('front/src/routes/(shop)/inscription/+page.svelte');
		assert.match(page, /\{step < STEP_COUNT \? 'Continuer' : 'Créer mon compte'\}/);
		assert.match(read('front/src/lib/account/registration.ts'), /case 2:[\s\S]*?isValidSiret\(values\.siret\)[\s\S]*?case 3:/);
	});

	it('écrit les textes sans tiret long ni demi-cadratin', () => {
		const texts = [...DEMO_NEWS_GROUPS.flatMap((group) => [group.title, group.subtitle]), ...DEMO_NEWS.flatMap((item) => [item.label, item.hint ?? ''])];
		for (const text of texts) assert.doesNotMatch(text, /[\u2013\u2014]/, text);
	});

	it('lit bien les pages CMS publiées des fixtures', () => {
		const slugs = publishedCmsSlugs();
		assert.ok(slugs.has('nos-documentations'));
		assert.ok(slugs.has('accueil'));
		assert.ok(!slugs.has('demo-ecarts-de-charte'));
		assert.ok(readdirSync(SHOP_ROUTES).includes('sav'));
	});
});
