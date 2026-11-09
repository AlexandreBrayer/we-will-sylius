// Une seule navigation à la fois (B9-4) : décision du garde, liens pris en charge, file d'attente, et
// branchement dans la coque. `npm test`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
	STUCK_MS,
	createNavigationGuard,
	decideClick,
	routedTarget,
	type AnchorLike,
	type ClickLike
} from '../src/lib/layout/navigationGuard.ts';

const here = new URL('https://boutique.example/produits/pompe-a-chaleur-air-air');
const at = (path: string) => new URL(path, here);

function click(overrides: Partial<ClickLike> = {}): ClickLike & { prevented: number; stopped: number } {
	const event = {
		button: 0,
		metaKey: false,
		ctrlKey: false,
		shiftKey: false,
		altKey: false,
		defaultPrevented: false,
		target: null,
		prevented: 0,
		stopped: 0,
		preventDefault() {
			event.prevented += 1;
			event.defaultPrevented = true;
		},
		stopPropagation() {
			event.stopped += 1;
		},
		...overrides
	};
	return event;
}

function anchor(href: unknown, attributes: Record<string, string> = {}, reloadZone = false): AnchorLike {
	return {
		href,
		target: attributes.target ?? '',
		hasAttribute: (name) => name in attributes,
		getAttribute: (name) => attributes[name] ?? null,
		closest: (selector) => (reloadZone && selector.startsWith('[data-sveltekit-reload]') ? {} : null)
	};
}

describe('decideClick', () => {
	it('laisse passer un clic sans navigation en cours', () => {
		assert.equal(decideClick(null, at('/panier'), 0), 'pass');
	});

	it('ignore un second clic vers la même adresse (double clic)', () => {
		assert.equal(decideClick(at('/commande/adresse'), at('/commande/adresse'), 400), 'ignore');
		assert.equal(decideClick(at('/commande/adresse'), at('/commande/adresse#haut'), 400), 'ignore');
	});

	it('met en attente un clic vers une autre adresse', () => {
		assert.equal(decideClick(at('/commande/adresse'), at('/trouver-un-installateur'), 400), 'queue');
		assert.equal(decideClick(at('/produits?page=2'), at('/produits?page=3'), 400), 'queue');
	});

	it('recharge la page quand la navigation en cours est bloquée', () => {
		assert.equal(decideClick(at('/commande/adresse'), at('/produits'), STUCK_MS + 1), 'reload');
		assert.equal(decideClick(at('/commande/adresse'), at('/commande/adresse'), STUCK_MS + 1), 'reload');
	});
});

describe('routedTarget', () => {
	it('rend la cible d’un lien interne', () => {
		assert.equal(routedTarget(click(), anchor('https://boutique.example/panier'), here)?.pathname, '/panier');
	});

	it('lit le href d’un lien SVG par son attribut', () => {
		assert.equal(routedTarget(click(), anchor({ baseVal: '/sav' }, { href: '/sav' }), here)?.pathname, '/sav');
		const svgBlank = { ...anchor({ baseVal: '/sav' }, { href: '/sav', target: '_blank' }), target: { baseVal: '_blank' } };
		assert.equal(routedTarget(click(), svgBlank, here), null);
	});

	it('laisse au navigateur ce que le routeur ne prend pas', () => {
		const cases: Array<[ClickLike, AnchorLike | null]> = [
			[click(), null],
			[click({ defaultPrevented: true }), anchor('/panier')],
			[click({ button: 1 }), anchor('/panier')],
			[click({ metaKey: true }), anchor('/panier')],
			[click({ ctrlKey: true }), anchor('/panier')],
			[click({ shiftKey: true }), anchor('/panier')],
			[click({ altKey: true }), anchor('/panier')],
			[click(), anchor('/panier', { target: '_blank' })],
			[click(), anchor('/media/notice.pdf', { download: '' })],
			[click(), anchor('/panier', { rel: 'nofollow external' })],
			[click(), anchor('/panier', {}, true)],
			[click(), anchor('https://autre.example/panier')],
			[click(), anchor('#contenu')],
			[click(), anchor('/produits/pompe-a-chaleur-air-air#filtres')],
			[click(), anchor('')]
		];
		for (const [event, link] of cases) assert.equal(routedTarget(event, link, here), null);
	});
});

describe('createNavigationGuard', () => {
	function harness() {
		const state = { pending: null as URL | null, location: here, now: 1000, went: [] as string[], reloaded: [] as string[] };
		const guard = createNavigationGuard({
			pending: () => state.pending,
			location: () => state.location,
			go: (url) => state.went.push(url.pathname + url.search),
			reload: (url) => state.reloaded.push(url.pathname),
			now: () => state.now
		});
		return { state, guard };
	}

	it('ne touche à rien sans navigation en cours', () => {
		const { guard } = harness();
		const event = click();
		guard.onClick(event, anchor('/panier'));
		assert.equal(event.prevented, 0);
		assert.equal(event.stopped, 0);
	});

	it('double clic : le second clic est absorbé, rien ne repart à la fin', () => {
		const { state, guard } = harness();
		guard.begin();
		state.pending = at('/commande/adresse');
		const second = click();
		guard.onClick(second, anchor('/commande/adresse'));
		assert.equal(second.prevented, 1);
		assert.equal(second.stopped, 1);
		state.pending = null;
		state.location = at('/commande/adresse');
		guard.settle();
		assert.deepEqual(state.went, []);
	});

	it('clic vers ailleurs pendant la navigation : il part après, le dernier l’emporte', () => {
		const { state, guard } = harness();
		guard.begin();
		state.pending = at('/trouver-un-installateur');
		guard.onClick(click(), anchor('/catalogues'));
		guard.onClick(click(), anchor('/nos-documentations'));
		assert.equal(guard.queued()?.pathname, '/nos-documentations');
		assert.deepEqual(state.went, []);
		state.pending = null;
		state.location = at('/trouver-un-installateur');
		guard.settle();
		assert.deepEqual(state.went, ['/nos-documentations']);
		assert.equal(guard.queued(), null);
	});

	it('un double clic après un clic ailleurs annule l’attente', () => {
		const { state, guard } = harness();
		guard.begin();
		state.pending = at('/commande/adresse');
		guard.onClick(click(), anchor('/produits'));
		guard.onClick(click(), anchor('/commande/adresse'));
		state.pending = null;
		state.location = at('/commande/adresse');
		guard.settle();
		assert.deepEqual(state.went, []);
	});

	it('navigation bloquée au-delà de STUCK_MS : le clic recharge la page', () => {
		const { state, guard } = harness();
		guard.begin();
		state.pending = at('/commande/adresse');
		state.now += STUCK_MS + 1;
		const event = click();
		guard.onClick(event, anchor('/panier'));
		assert.equal(event.prevented, 1);
		assert.deepEqual(state.reloaded, ['/panier']);
		assert.equal(guard.queued(), null);
	});
});

describe('coque de la boutique', () => {
	it('branche le garde avant le routeur, au départ et à la fin de chaque navigation', () => {
		const layout = readFileSync(new URL('../src/routes/+layout.svelte', import.meta.url), 'utf8');
		assert.match(layout, /createNavigationGuard\(/);
		assert.match(layout, /pending: \(\) => navigating\.to\?\.url \?\? null/);
		assert.match(layout, /document\.addEventListener\('click', onClick, true\)/);
		assert.match(layout, /beforeNavigate\(\(\) => guard\.begin\(\)\)/);
		assert.match(layout, /afterNavigate\(\(\) => guard\.settle\(\)\)/);
	});
});
