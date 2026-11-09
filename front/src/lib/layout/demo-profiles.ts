// Profils de la démonstration : ce que chacun peut faire (règle du site actuel de Yack, Espace Pro et Espace
// E-commerce, reprise en V1) et son compte de démo (fixtures). Utilisés par la barre de démo et la page de connexion.
import type { Viewer } from '#lib/types.js';

export interface DemoProfileInfo {
	key: 'visiteur' | 'pro' | 'ecom' | 'admin';
	label: string;
	can: string;
	cannot: string;
	/** Compte de démo (vide pour le visiteur). */
	account: string;
	/** Précision affichée après le compte (niveau dans le builder pour un compte du back-office). */
	accountNote?: string;
	/** Autres comptes de démo du même profil (back-office : un compte par niveau dans le builder). */
	otherAccounts?: { account: string; note: string }[];
	current: (viewer: Viewer) => boolean;
}

/** Mot de passe commun des comptes de démo (fixtures), affiché seulement quand la barre de démo est active. */
export const DEMO_PASSWORD = 'yack';

/** Ligne d'un compte de démo dans l'infobulle de la barre : « compte / mot de passe (précision) ». */
export function demoAccountLine(account: string, note?: string): string {
	return `${account} / ${DEMO_PASSWORD}${note ? ` (${note})` : ''}`;
}

export const DEMO_PROFILES: DemoProfileInfo[] = [
	{
		key: 'visiteur',
		label: 'Visiteur',
		can: 'Parcourt le catalogue en vitrine.',
		cannot: 'Ni prix, ni panier, ni documentation technique.',
		account: '',
		current: (v) => !v.authenticated
	},
	{
		key: 'pro',
		label: 'PRO',
		can: 'Installateur : catalogue, documentation technique (espace en cours d’intégration), catalogues PDF.',
		cannot: 'Ni prix, ni commande : il peut demander l’accès e-commerce depuis son compte.',
		account: 'pro@yack.test',
		current: (v) => v.profile === 'pro'
	},
	{
		key: 'ecom',
		label: 'ECOM',
		can: 'Client e-commerce : ses prix HT, le stock, le panier, la commande et le paiement en ligne.',
		cannot: '',
		account: 'ecom@yack.test',
		current: (v) => v.profile === 'ecom'
	},
	{
		key: 'admin',
		label: 'Admin (back-office)',
		can: 'Équipe Yack : back-office (produits, commandes, comptes et demandes d’ouverture, pages du site).',
		cannot: 'S’ouvre dans un nouvel onglet ; la boutique reste dans le profil en cours.',
		account: 'admin@yack.test',
		accountNote: 'builder : niveau Libre',
		otherAccounts: [{ account: 'editeur@yack.test', note: 'builder : niveau Charte' }],
		current: () => false
	}
];
