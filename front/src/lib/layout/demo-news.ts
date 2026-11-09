// Menu « Nouveautés » de la barre de démo : les écrans du site qui n'existent pas sur le site actuel de Yack, rangés
// en quatre groupes pour que le client distingue toujours ce qui est prévu au devis de ce qui ne l'est pas. Une
// entrée = un groupe, un libellé, un lien, une précision et les profils qui voient l'entrée : changer `group` suffit
// à déplacer une entrée d'un groupe à l'autre. Seuls les écrans qui existent et répondent sont listés.
import type { DemoProfileInfo } from './demo-profiles.js';

export type DemoNewsGroupKey = 'devis-v1' | 'devis-v2' | 'options' | 'hors-devis';

/** Profil de la barre de démo ; `admin` : écran du back-office. */
export type DemoProfileKey = DemoProfileInfo['key'];

export interface DemoNewsGroup {
	key: DemoNewsGroupKey;
	title: string;
	/** Sous-titre affiché sous le titre du groupe. */
	subtitle: string;
}

export interface DemoNewsItem {
	group: DemoNewsGroupKey;
	label: string;
	href: string;
	/** Précision affichée sous le libellé : ce qu'on y montre, ou ce qui manque à l'écran. */
	hint?: string;
	/**
	 * Profils qui voient l'entrée et peuvent ouvrir l'écran. `admin` marque un écran du back-office : il est montré
	 * dans tous les profils de la boutique (le bouton « Admin » de la barre l'est aussi) et s'ouvre dans un nouvel
	 * onglet, sur la connexion du back-office si personne n'y est connecté.
	 */
	profiles: readonly DemoProfileKey[];
}

/** Groupes, dans l'ordre du menu. */
export const DEMO_NEWS_GROUPS: readonly DemoNewsGroup[] = [
	{ key: 'devis-v1', title: 'Prévu au devis, V1', subtitle: 'Compris dans le devis, première version du site.' },
	{ key: 'devis-v2', title: 'Prévu au devis, V2', subtitle: 'Compris dans le devis, deuxième version du site.' },
	{
		key: 'options',
		title: 'Options chiffrées de la proposition',
		subtitle: 'Chiffrées à part dans la proposition : non incluses dans le prix de base.'
	},
	{
		key: 'hors-devis',
		title: 'Pistes pour la suite, hors devis',
		subtitle: 'Ni chiffrées ni incluses dans le prix de base : des pistes à discuter.'
	}
];

const ALL: readonly DemoProfileKey[] = ['visiteur', 'pro', 'ecom'];

export const DEMO_NEWS: readonly DemoNewsItem[] = [
	{ group: 'devis-v1', label: 'Trouver un installateur', href: '/trouver-un-installateur', hint: 'annuaire filtrable et carte', profiles: ALL },
	{
		group: 'devis-v1',
		label: 'Inscription professionnelle',
		href: '/inscription?profil=pro',
		hint: 'SIRET vérifié au clic sur « Continuer », puis à la création du compte ; pas de dépôt du Kbis dans cette démo',
		profiles: ['visiteur']
	},
	{
		group: 'devis-v1',
		label: 'Paiement : carte et virement à 15 jours avec escompte, virement à 30 jours',
		href: '/panier',
		hint: 'étape Paiement de la commande, avec un article au panier ; pas d’encours à l’écran',
		profiles: ['ecom']
	},
	{ group: 'devis-v1', label: 'Documentation selon le compte', href: '/nos-documentations', hint: 'en-tête selon le profil ; page de démonstration, contenu fictif', profiles: ALL },
	{
		group: 'devis-v1',
		label: 'Demande d’ouverture à la commande',
		href: '/compte/acces-commande',
		hint: 'un compte PRO demande à commander en ligne',
		profiles: ['pro']
	},
	{ group: 'devis-v1', label: 'Tableau de bord des ventes', href: '/admin/', hint: 'ventes, inscriptions PRO, demandes en attente', profiles: ['admin'] },
	{ group: 'devis-v2', label: 'Clik&Yack Parts', href: '/sav', hint: 'pièces par numéro de série, vues éclatées', profiles: ALL },
	{ group: 'devis-v2', label: 'Éditeur de pages par blocs', href: '/cms-admin/builder', hint: 'pages du site, aux couleurs de la charte', profiles: ['admin'] },
	{
		group: 'devis-v2',
		label: 'Templates des fiches produit et des pages catégorie',
		href: '/cms-admin/builder/templates',
		hint: 'même éditeur, affecté à des produits ou des catégories',
		profiles: ['admin']
	},
	{ group: 'options', label: 'La pièce par une photo', href: '/sav#photo-plaque', hint: 'photo de la plaque signalétique', profiles: ALL },
	{
		group: 'options',
		label: 'Assistant documentation',
		href: '/assistance?ref=SRK25ZS-W',
		hint: 'question posée aux manuels indexés d’un modèle, réponse avec la page source ; comptes connectés',
		profiles: ALL
	},
	{
		group: 'options',
		label: 'Fonctions intelligentes',
		href: '/fonctions-intelligentes',
		hint: 'liste des options, dont commande depuis un fichier et contrôle du Kbis, sans écran dans cette démo',
		profiles: ALL
	},
	{
		group: 'hors-devis',
		label: 'Manuels et notices',
		href: '/assistance',
		hint: 'manuels officiels d’un appareil par référence, n° de série ou photo',
		profiles: ALL
	}
];

/** Chemin du module « Manuels et notices » : ses entrées disparaissent quand il est coupé (YACK_ASSISTANCE=0). */
const ASSISTANCE_PATH = '/assistance';

/** Écran du back-office (profil `admin`) : ouvert dans un nouvel onglet. */
export function isBackOffice(item: DemoNewsItem): boolean {
	return item.profiles.includes('admin');
}

/**
 * Entrées visibles dans un profil de la boutique (`visiteur`, `pro` ou `ecom` : le back-office n'a pas de session
 * sur la boutique), dans l'ordre du fichier.
 */
export function demoNewsFor(profile: DemoProfileKey, options: { assistance: boolean } = { assistance: true }): DemoNewsItem[] {
	return DEMO_NEWS.filter(
		(item) =>
			(isBackOffice(item) || item.profiles.includes(profile)) &&
			(options.assistance || !(item.href === ASSISTANCE_PATH || item.href.startsWith(`${ASSISTANCE_PATH}?`)))
	);
}

/** Groupes non vides, dans l'ordre du menu, avec leurs entrées. */
export function demoNewsByGroup(items: readonly DemoNewsItem[]): { group: DemoNewsGroup; items: DemoNewsItem[] }[] {
	return DEMO_NEWS_GROUPS.map((group) => ({ group, items: items.filter((item) => item.group === group.key) })).filter((entry) => entry.items.length > 0);
}
