// Index des fonctions intelligentes (page /fonctions-intelligentes). Chaque fonction vit là où elle sert ;
// cette liste ne fait que les recenser et y renvoyer. Aucune règle ici : les droits restent portés par l'API.

/** `option` : option chiffrée de la proposition (p. 16) ; `nouveaute` : idée hors devis, en essai. */
export type SmartStatus = 'option' | 'nouveaute';

export interface SmartFunction {
	key: string;
	title: string;
	/** Ce que ça change pour le client, en une phrase. */
	why: string;
	/** Où la fonction apparaît dans le site. */
	where: string;
	/** Lien vers la fonction en situation ; null tant qu'elle n'est pas branchée dans ce site. */
	url: string | null;
	/** Qui la voit (affiché, non appliqué ici). */
	audience: string;
	status: SmartStatus;
}

export const SMART_FUNCTIONS: SmartFunction[] = [
	{
		key: 'sav-photo',
		title: 'La pièce par une photo',
		why: 'Une photo de la plaque signalétique retrouve l’appareil et ouvre la bonne vue éclatée.',
		where: 'SAV & pièces détachées',
		url: '/sav',
		audience: 'Tous les visiteurs',
		status: 'option'
	},
	{
		key: 'devis-fichier',
		title: 'Commande depuis un fichier',
		why: 'Un devis PDF devient un panier, ligne par ligne, avec les écarts signalés.',
		where: 'Panier',
		url: null,
		audience: 'Comptes autorisés à commander',
		status: 'option'
	},
	{
		key: 'assistant-documentation',
		title: 'Assistant documentation',
		why: 'Une référence ou une photo de la plaque donne les manuels officiels du modèle ; une question reçoit une réponse tirée de leurs pages, avec la page source.',
		where: 'Manuels et notices',
		url: '/assistance',
		audience: 'Tous les visiteurs ; question : comptes connectés (PRO et ECOM)',
		status: 'option'
	},
	{
		key: 'kbis',
		title: 'Contrôle du Kbis',
		why: 'Le Kbis déposé à l’inscription est lu et comparé au compte ; votre équipe garde la décision.',
		where: 'Inscription professionnelle et back-office',
		url: null,
		audience: 'Équipe Yack',
		status: 'option'
	}
];

export const STATUS_LABELS: Record<SmartStatus, string> = {
	option: 'Option chiffrée',
	nouveaute: 'Nouveauté en essai'
};
