// Une seule navigation à la fois dans la boutique.
//
// Avec Svelte asynchrone, SvelteKit garde la page affichée tant que la suivante n'est pas prête. Une
// seconde navigation lancée pendant cette attente (double clic sur un lien, clic sur un autre lien) se
// superpose à la première : l'ancienne page reçoit les `params` et `page` de la route suivante et
// relance ses requêtes avec (getTaxonPage sans `path`, getCmsPage sans `slug` : refus 400), des
// composants quittés restent vivants et redemandent leurs données, et selon l'ordre d'arrivée des
// réponses on voit « Une erreur est survenue » qui reste, l'ancienne page figée, ou l'ancienne page
// empilée au-dessus de la nouvelle. SvelteKit n'appelle pas `beforeNavigate` pendant une navigation
// en cours : le garde intercepte donc le clic lui-même, avant le routeur.
//
// Règle : pendant une navigation, un clic vers la même adresse est ignoré (double clic) ; un clic vers
// une autre adresse est mis en attente et part dès que la navigation en cours est terminée (le dernier
// clic l'emporte). Si la navigation en cours dure plus de STUCK_MS, le clic charge la page en entier.

/** Au-delà, la navigation en cours est tenue pour bloquée : le clic suivant recharge la page. */
export const STUCK_MS = 8000;

export type ClickDecision = 'pass' | 'ignore' | 'queue' | 'reload';

/** Clé d'une adresse pour comparer deux navigations : chemin et requête, sans l'ancre. */
export function navigationKey(url: URL): string {
	return url.pathname + url.search;
}

/**
 * Que faire d'un clic vers `target` (lien que le routeur prendrait en charge) quand `pending` est la
 * cible de la navigation en cours (null : aucune) et dure depuis `elapsedMs`.
 */
export function decideClick(pending: URL | null, target: URL, elapsedMs: number): ClickDecision {
	if (pending === null) return 'pass';
	if (elapsedMs > STUCK_MS) return 'reload';
	return navigationKey(pending) === navigationKey(target) ? 'ignore' : 'queue';
}

/** Ce que le garde lit du clic (sous-ensemble de MouseEvent, pour les tests). */
export interface ClickLike {
	button: number;
	metaKey: boolean;
	ctrlKey: boolean;
	shiftKey: boolean;
	altKey: boolean;
	defaultPrevented: boolean;
	target: EventTarget | null;
	preventDefault(): void;
	stopPropagation(): void;
}

/** Lien du clic (sous-ensemble d'HTMLAnchorElement, pour les tests). */
export interface AnchorLike {
	/** Chaîne pour un lien HTML ; objet (SVGAnimatedString) pour un lien SVG, lu alors par `getAttribute`. */
	href: unknown;
	/** Même chose pour la cible (`_blank`...). */
	target: unknown;
	hasAttribute(name: string): boolean;
	getAttribute(name: string): string | null;
	closest(selector: string): unknown;
}

/**
 * Cible du clic si c'est une navigation que le routeur de SvelteKit ferait lui-même (lien interne, clic
 * gauche sans touche, ni nouvel onglet, ni téléchargement, ni `data-sveltekit-reload`, ni lien qui ouvre un
 * panneau), sinon null.
 * Un lien vers une ancre de la même page n'est pas une navigation : null.
 */
export function routedTarget(event: ClickLike, anchor: AnchorLike | null, location: URL): URL | null {
	if (anchor === null || event.defaultPrevented) return null;
	if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return null;
	const target = typeof anchor.target === 'string' ? anchor.target : anchor.getAttribute('target');
	if (target && target !== '_self') return null;
	// Lien qui ouvre un panneau avec JavaScript (« Panier » ouvre le mini-panier) : pas une navigation.
	if (anchor.hasAttribute('aria-haspopup')) return null;
	if (anchor.hasAttribute('download') || (anchor.getAttribute('rel') ?? '').split(/\s+/).includes('external')) return null;
	if (anchor.closest('[data-sveltekit-reload]:not([data-sveltekit-reload="off"])')) return null;
	const href = typeof anchor.href === 'string' ? anchor.href : anchor.getAttribute('href');
	if (!href) return null;
	let url: URL;
	try {
		url = new URL(href, location);
	} catch {
		return null;
	}
	if (url.origin !== location.origin) return null;
	if (url.hash && navigationKey(url) === navigationKey(location)) return null;
	return url;
}

export interface GuardDeps {
	/** Cible de la navigation en cours (`navigating.to.url` de `$app/state`), null sans navigation. */
	pending(): URL | null;
	/** Adresse affichée. */
	location(): URL;
	/** Navigation par le routeur (`goto`). */
	go(url: URL): void;
	/** Chargement complet de la page. */
	reload(url: URL): void;
	now(): number;
}

export interface NavigationGuard {
	/** À appeler au départ d'une navigation (`beforeNavigate`) : point de départ du délai STUCK_MS. */
	begin(): void;
	/** À brancher en capture sur `document` (avant le routeur, qui écoute en bulle). */
	onClick(event: ClickLike, anchor: AnchorLike | null): void;
	/** À appeler quand la navigation en cours se termine (réussie, annulée ou en erreur). */
	settle(): void;
	/** Cible en attente (tests). */
	queued(): URL | null;
}

export function createNavigationGuard(deps: GuardDeps): NavigationGuard {
	let queued: URL | null = null;
	let pendingKey: string | null = null;
	let pendingSince = 0;

	return {
		begin() {
			pendingKey = null;
			pendingSince = deps.now();
		},
		onClick(event, anchor) {
			const target = routedTarget(event, anchor, deps.location());
			if (target === null) return;
			const pending = deps.pending();
			if (pending === null) return;
			if (pendingKey === null) {
				pendingKey = navigationKey(pending);
				// Sans `begin()` (navigation lancée par `goto`), le délai part du premier clic retenu.
				if (pendingSince === 0) pendingSince = deps.now();
			}
			const decision = decideClick(pending, target, deps.now() - pendingSince);
			if (decision === 'pass') return;
			event.preventDefault();
			event.stopPropagation();
			if (decision === 'reload') {
				queued = null;
				deps.reload(target);
			} else if (decision === 'queue') {
				queued = target;
			} else {
				// Double clic : la navigation demandée est déjà en route ; un clic en attente vers ailleurs tombe.
				queued = null;
			}
		},
		settle() {
			pendingKey = null;
			pendingSince = 0;
			const next = queued;
			queued = null;
			if (next !== null && navigationKey(next) !== navigationKey(deps.location())) deps.go(next);
		},
		queued: () => queued
	};
}
