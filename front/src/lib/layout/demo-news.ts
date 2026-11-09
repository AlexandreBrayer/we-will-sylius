// Menu « Nouveautés » de la barre de démo : les écrans du site qui n'existent pas sur le site actuel de Yack, en deux
// colonnes pour que le client distingue toujours ce qui est au devis de ce qui ne l'est pas : « Dans le devis », puis
// « En plus du devis » (options chiffrées à part et pistes non chiffrées, chacune avec sa pastille de statut). Une
// entrée = un groupe (et un statut hors devis), un libellé court, un lien, une précision facultative et les profils
// qui voient l'entrée. Seuls les écrans qui existent et répondent sont listés.
import type { DemoProfileInfo } from './demo-profiles.js';

export type DemoNewsGroupKey = 'devis' | 'en-plus';

/** Statut d'une entrée hors devis : `option`, chiffrée à part dans la proposition ; `piste`, non chiffrée. */
export type DemoNewsStatus = 'option' | 'piste';

/** Profil de la barre de démo ; `admin` : écran du back-office. */
export type DemoProfileKey = DemoProfileInfo['key'];

export interface DemoNewsGroup {
	key: DemoNewsGroupKey;
	title: string;
	/** Statuts expliqués par la légende en bas de la colonne (aucune légende si la liste est vide). */
	legend: readonly DemoNewsStatus[];
}

/** Pastille de statut : texte de la pastille et sens donné par la légende. */
export interface DemoNewsStatusInfo {
	label: string;
	meaning: string;
}

interface DemoNewsItemBase {
	/** Libellé court, sur une ligne. */
	label: string;
	href: string;
	/** Précision en second plan, à la suite du libellé : seulement si elle aide (ce qui manque à l'écran, par exemple). */
	hint?: string;
	/**
	 * Profils qui voient l'entrée et peuvent ouvrir l'écran. `admin` marque un écran du back-office : il est montré
	 * dans tous les profils de la boutique (le bouton « Admin » de la barre l'est aussi) et s'ouvre dans un nouvel
	 * onglet, sur la connexion du back-office si personne n'y est connecté.
	 */
	profiles: readonly DemoProfileKey[];
}

/** Une entrée du devis n'a pas de statut ; une entrée en plus du devis en a toujours un. */
export type DemoNewsItem = DemoNewsItemBase & ({ group: 'devis'; status?: never } | { group: 'en-plus'; status: DemoNewsStatus });

/** Groupes, dans l'ordre des colonnes (de gauche à droite ; l'un sous l'autre sur téléphone). */
export const DEMO_NEWS_GROUPS: readonly DemoNewsGroup[] = [
	{ key: 'devis', title: 'Dans le devis', legend: [] },
	{ key: 'en-plus', title: 'En plus du devis', legend: ['option', 'piste'] }
];

export const DEMO_NEWS_STATUSES: Readonly<Record<DemoNewsStatus, DemoNewsStatusInfo>> = {
	option: { label: 'Option', meaning: 'chiffrée à part' },
	piste: { label: 'Piste', meaning: 'non chiffrée, à discuter' }
};

/** Marque des écrans du back-office, et ce qu'elle veut dire (lu par les lecteurs d'écran, en infobulle au survol). */
export const BACK_OFFICE_MARK = { label: 'BO', meaning: 'back-office, nouvel onglet' } as const;

const ALL: readonly DemoProfileKey[] = ['visiteur', 'pro', 'ecom'];

export const DEMO_NEWS: readonly DemoNewsItem[] = [
	{ group: 'devis', label: 'Trouver un installateur', href: '/trouver-un-installateur', hint: 'annuaire et carte', profiles: ALL },
	{ group: 'devis', label: 'Inscription PRO', href: '/inscription?profil=pro', hint: 'SIRET vérifié, sans dépôt du Kbis', profiles: ['visiteur'] },
	{ group: 'devis', label: 'Paiement et escompte', href: '/panier', hint: 'pas d’encours à l’écran', profiles: ['ecom'] },
	{ group: 'devis', label: 'Documentation selon le profil', href: '/nos-documentations', hint: 'contenu fictif', profiles: ALL },
	{ group: 'devis', label: 'Ouverture à la commande', href: '/compte/acces-commande', hint: 'demande d’un compte PRO', profiles: ['pro'] },
	{ group: 'devis', label: 'Pièces par n° de série', href: '/sav', hint: 'Clik&Yack Parts', profiles: ALL },
	{ group: 'devis', label: 'Tableau de bord', href: '/admin/', hint: 'ventes et inscriptions', profiles: ['admin'] },
	{ group: 'devis', label: 'Builder de pages', href: '/cms-admin/builder', profiles: ['admin'] },
	{ group: 'devis', label: 'Templates produit et catégorie', href: '/cms-admin/builder/templates', profiles: ['admin'] },
	{ group: 'en-plus', status: 'option', label: 'La pièce par une photo', href: '/sav#photo-plaque', hint: 'plaque signalétique', profiles: ALL },
	{ group: 'en-plus', status: 'option', label: 'Assistant documentation', href: '/assistance?ref=SRK25ZS-W', hint: 'comptes connectés', profiles: ALL },
	{ group: 'en-plus', status: 'option', label: 'Fonctions intelligentes', href: '/fonctions-intelligentes', hint: 'liste des options', profiles: ALL },
	{ group: 'en-plus', status: 'piste', label: 'Manuels et notices', href: '/assistance', hint: 'par référence ou photo', profiles: ALL },
	{
		group: 'en-plus',
		status: 'piste',
		label: 'Liberté de style encadrée',
		href: '/admin/yack/contenu/ecarts-de-charte',
		hint: 'niveau Libre',
		profiles: ['admin']
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

/** Groupes non vides, dans l'ordre des colonnes, avec leurs entrées. */
export function demoNewsByGroup(items: readonly DemoNewsItem[]): { group: DemoNewsGroup; items: DemoNewsItem[] }[] {
	return DEMO_NEWS_GROUPS.map((group) => ({ group, items: items.filter((item) => item.group === group.key) })).filter((entry) => entry.items.length > 0);
}
