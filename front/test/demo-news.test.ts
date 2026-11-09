// Menu « Nouveautés » de la barre de démo (src/lib/layout/demo-news.ts) : deux colonnes, statuts, entrées, liens et profils.
// Les liens sont contrôlés contre les routes du front, les pages CMS des fixtures et les routes du back-office. `npm test`.
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { describe, it } from 'node:test';
import { BACK_OFFICE_MARK, DEMO_NEWS, DEMO_NEWS_GROUPS, DEMO_NEWS_STATUSES, demoNewsByGroup, demoNewsFor, isBackOffice } from '../src/lib/layout/demo-news.ts';
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
	const shopProfiles = ['visiteur', 'pro', 'ecom'] as const;

	it('a deux groupes connus, dans l’ordre des colonnes : « Dans le devis » puis « En plus du devis »', () => {
		assert.deepEqual(groupKeys, ['devis', 'en-plus']);
		assert.deepEqual(
			DEMO_NEWS_GROUPS.map((group) => group.title),
			['Dans le devis', 'En plus du devis']
		);
	});

	it('donne à chaque entrée un groupe connu, un libellé et au moins un profil connu, sans groupe vide', () => {
		for (const item of DEMO_NEWS) {
			assert.ok(groupKeys.includes(item.group), `${item.href} : groupe ${item.group}`);
			assert.ok(item.label.trim().length > 0, item.href);
			assert.ok(item.profiles.length > 0, item.href);
			for (const profile of item.profiles) assert.ok(profileKeys.includes(profile), `${item.href} : profil ${profile}`);
		}
		for (const key of groupKeys) assert.ok(DEMO_NEWS.some((item) => item.group === key), `groupe vide : ${key}`);
	});

	it('ne donne un statut (Option ou Piste) qu’aux entrées en plus du devis, et toujours', () => {
		for (const item of DEMO_NEWS) {
			if (item.group === 'devis') assert.equal(item.status, undefined, item.href);
			else assert.ok(item.status !== undefined && item.status in DEMO_NEWS_STATUSES, item.href);
		}
		assert.deepEqual(
			Object.values(DEMO_NEWS_STATUSES).map((status) => status.label),
			['Option', 'Piste']
		);
	});

	it('explique en une phrase, en bas de la colonne « En plus du devis », chaque statut employé', () => {
		const used = [...new Set(DEMO_NEWS.flatMap((item) => (item.status ? [item.status] : [])))];
		const extra = DEMO_NEWS_GROUPS.find((group) => group.key === 'en-plus');
		assert.deepEqual([...(extra?.legend ?? [])].sort(), used.sort());
		assert.deepEqual(DEMO_NEWS_GROUPS.find((group) => group.key === 'devis')?.legend, []);
		const sentence = (extra?.legend ?? []).map((status) => `${DEMO_NEWS_STATUSES[status].label} ${DEMO_NEWS_STATUSES[status].meaning}`).join(' ; ') + '.';
		assert.equal(sentence, 'Option chiffrée à part ; Piste non chiffrée, à discuter.');
		assert.equal(sentence.split('.').length, 2, 'une seule phrase');
	});

	it('n’a aucun lien ni libellé en double', () => {
		const hrefs = DEMO_NEWS.map((item) => item.href);
		assert.equal(new Set(hrefs).size, hrefs.length, hrefs.join(', '));
		const labels = DEMO_NEWS.map((item) => item.label);
		assert.equal(new Set(labels).size, labels.length, labels.join(', '));
	});

	it('garde des libellés courts, une ligne par entrée', () => {
		for (const item of DEMO_NEWS) {
			assert.ok(item.label.length <= 30, `libellé trop long : ${item.label}`);
			assert.ok(item.label.length + (item.hint?.length ?? 0) <= 50, `entrée trop longue : ${item.label}`);
		}
	});

	it('ne promet ni encours ni dépôt du Kbis', () => {
		for (const text of DEMO_NEWS.flatMap((item) => [item.label, item.hint ?? ''])) {
			if (/encours/i.test(text)) assert.match(text, /^pas d’encours à l’écran$/, text);
			if (/kbis/i.test(text)) assert.match(text, /sans dépôt du Kbis/, text);
		}
		const signup = DEMO_NEWS.find((item) => item.href === '/inscription?profil=pro');
		assert.match(signup?.hint ?? '', /sans dépôt du Kbis/);
		assert.match(DEMO_NEWS.find((item) => item.href === '/panier')?.hint ?? '', /pas d’encours à l’écran/);
	});

	it('ne parle d’escompte que si la configuration en accorde un', () => {
		// config/packages/yack.yaml : escompte par moyen de paiement (carte et virement à 15 jours : 2 %).
		const yaml = read('config/packages/yack.yaml');
		const rates = ['carte_bancaire', 'virement_15j', 'virement_echeance'].map((code) => Number(yaml.match(new RegExp(`\\n {8}${code}: (\\d+)\\n`))?.[1] ?? 0));
		assert.ok(rates.some((rate) => rate > 0), rates.join(', '));
		assert.equal(DEMO_NEWS.find((item) => item.href === '/panier')?.label, 'Paiement et escompte');
	});

	it('ne mène qu’à des écrans qui existent', () => {
		for (const item of DEMO_NEWS) {
			assert.match(item.href, /^\/(?!\/)/, item.href);
			assert.ok(routeExists(item.href), `écran introuvable : ${item.href}`);
		}
	});

	it('marque comme back-office les seules entrées du profil admin, et elles seules mènent au back-office', () => {
		for (const item of DEMO_NEWS) {
			const backOfficePath = item.href.startsWith('/admin/') || item.href.startsWith('/cms-admin/');
			assert.equal(isBackOffice(item), backOfficePath, item.href);
			if (isBackOffice(item)) assert.deepEqual(item.profiles, ['admin'], item.href);
		}
		assert.equal(BACK_OFFICE_MARK.label, 'BO');
		assert.match(BACK_OFFICE_MARK.meaning, /back-office, nouvel onglet/);
	});

	it('montre à chaque profil ses entrées et les écrans du back-office', () => {
		const labels = (profile: (typeof shopProfiles)[number]) => demoNewsFor(profile).map((item) => item.label);
		assert.ok(labels('visiteur').includes('Inscription PRO'));
		assert.ok(!labels('pro').includes('Inscription PRO'));
		assert.ok(!labels('ecom').includes('Inscription PRO'));
		assert.ok(labels('ecom').includes('Paiement et escompte'));
		assert.ok(!labels('pro').includes('Paiement et escompte'));
		assert.ok(!labels('visiteur').includes('Paiement et escompte'));
		assert.ok(labels('pro').includes('Ouverture à la commande'));
		assert.ok(!labels('ecom').includes('Ouverture à la commande'));
		assert.ok(!labels('visiteur').includes('Ouverture à la commande'));
		for (const profile of shopProfiles) {
			const visible = demoNewsFor(profile);
			assert.ok(DEMO_NEWS.filter(isBackOffice).every((item) => visible.includes(item)), profile);
			assert.ok(visible.every((item) => isBackOffice(item) || item.profiles.includes(profile)), profile);
			assert.equal(visible.length, 12, profile);
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

	it('remplit les deux colonnes pour chaque profil, module des manuels actif ou coupé', () => {
		for (const profile of shopProfiles) {
			for (const assistance of [true, false]) {
				const grouped = demoNewsByGroup(demoNewsFor(profile, { assistance }));
				assert.deepEqual(
					grouped.map(({ group }) => group.key),
					['devis', 'en-plus'],
					`${profile}, manuels ${assistance}`
				);
				for (const { group, items } of grouped) assert.ok(items.every((item) => item.group === group.key), group.key);
			}
		}
		assert.deepEqual(demoNewsByGroup([{ group: 'en-plus', status: 'piste', label: 'x', href: '/x', profiles: ['pro'] }]).map(({ group }) => group.key), ['en-plus']);
	});

	it('pose les deux colonnes côte à côte à partir de 768 px, l’une sous l’autre en dessous', () => {
		const scss = read('assets/shop/styles/_demo.scss');
		const menu = scss.slice(scss.indexOf('    &__menu {'), scss.indexOf('    &__group {'));
		assert.match(menu, /@media \(min-width: 768px\) \{[^}]*grid-auto-flow: column;[^}]*\}/);
		assert.doesNotMatch(menu.replace(/@media \(min-width: 768px\) \{[^}]*\}/, ''), /grid-auto-flow: column|grid-template-columns/);
		const bar = read('front/src/lib/layout/DemoBar.svelte');
		assert.match(bar, /role="group"\s+aria-labelledby="yack-demo-news-\{group\.key\}"/);
		assert.match(bar, /aria-describedby=\{group\.legend\.length > 0 \? `yack-demo-news-\$\{group\.key\}-legende` : undefined\}/);
	});

	it('dit que l’inscription PRO vérifie le SIRET, sans prétendre le contrôler à la saisie', () => {
		// Le SIRET est vérifié par validateStep(2) au clic sur le bouton de l'étape (« Continuer »), puis par l'API à
		// l'envoi final (Domain\Siret) ; jamais pendant la frappe. La précision du menu reste vraie sans dire quand.
		const hint = DEMO_NEWS.find((item) => item.href === '/inscription?profil=pro')?.hint ?? '';
		assert.match(hint, /^SIRET vérifié(,|$)/);
		assert.doesNotMatch(hint, /saisie|frappe|en direct|instantan/i);
		const page = read('front/src/routes/(shop)/inscription/+page.svelte');
		assert.match(page, /\{step < STEP_COUNT \? 'Continuer' : 'Créer mon compte'\}/);
		assert.match(read('front/src/lib/account/registration.ts'), /case 2:[\s\S]*?isValidSiret\(values\.siret\)[\s\S]*?case 3:/);
	});

	it('écrit les textes sans tiret long ni demi-cadratin', () => {
		const texts = [
			...DEMO_NEWS_GROUPS.map((group) => group.title),
			...Object.values(DEMO_NEWS_STATUSES).flatMap((status) => [status.label, status.meaning]),
			BACK_OFFICE_MARK.meaning,
			...DEMO_NEWS.flatMap((item) => [item.label, item.hint ?? ''])
		];
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
