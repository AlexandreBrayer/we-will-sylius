// Nouveautés du cahier des charges montrées dans la barre de démo, comme le menu « Nouveautés » de la barre de profils
// de la maquette (mode Demain) : chaque entrée porte la section du cahier des charges qu'elle réalise et mène à la
// page du site qui la montre. Seules les pages qui existent dans le site sont listées.
import type { Viewer } from '#lib/types.js';

export interface DemoNewsItem {
	/** Section du cahier des charges (« + » : option hors cahier des charges). */
	cdc: string;
	label: string;
	href: string;
	/** Précision affichée sous le libellé quand la page n'est pas l'écran lui-même. */
	hint?: string;
	/** Profils qui voient l'entrée ; tous si absent. */
	visible?: (viewer: Viewer) => boolean;
}

export const DEMO_NEWS: DemoNewsItem[] = [
	{ cdc: '+', label: 'Fonctions intelligentes', href: '/fonctions-intelligentes' },
	{ cdc: '3.2', label: 'Clik&Yack Parts', href: '/sav', hint: 'pièces par numéro de série, vues éclatées' },
	{
		cdc: '3.7',
		label: 'Nouveau paiement',
		href: '/panier',
		hint: 'étape Paiement de la commande',
		visible: (v) => v.canOrder
	},
	{ cdc: '3.10', label: 'Trouver un installateur', href: '/trouver-un-installateur' }
];

/** Entrées visibles pour le profil en cours. */
export function demoNewsFor(viewer: Viewer): DemoNewsItem[] {
	return DEMO_NEWS.filter((item) => item.visible?.(viewer) ?? true);
}
