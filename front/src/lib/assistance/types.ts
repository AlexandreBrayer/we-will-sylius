// Manuels et notices (/assistance) : formes de GET /api/v2/shop/yack/assistance/manuals (docs/API_FRONT.md,
// section 13). Le front les reçoit par `toManualSearch` (#lib/assistance/assistance.ts), qui écarte ce qui est mal formé.

/** Niveau de correspondance, du plus sûr au moins sûr. */
export type MatchLevel = 'exact' | 'serie' | 'famille' | 'aucun';

/** Pourquoi un document est rangé à son niveau. */
export type MatchReason = 'reference_identique' | 'variante_suffixe' | 'autre_puissance' | 'meme_type_appareil';

/** Type de document (ordre d'affichage : `MANUAL_TYPES` de #lib/assistance/assistance.ts). */
export type ManualType = 'installation' | 'utilisation' | 'technique' | 'service' | 'fiche_technique' | 'pieces' | 'telecommande' | 'brochure' | 'autre';

/** Origine de la marque : lue sur la plaque, déduite de la référence, ou donnée par le catalogue. */
export type BrandSource = 'plaque' | 'deduite' | 'catalogue';

/** Référence comprise par l'API. */
export interface SearchReference {
	/** Texte saisi ou lu, tel quel. */
	read: string;
	/** Forme canonique (SRK25ZS-W). */
	canonical: string;
	/** Découpée en préfixe, puissance, série, suffixe. */
	structured: boolean;
	/** Corrigée à la lecture (SRK2SZS-W compris SRK25ZS-W). */
	corrected: boolean;
	brand: string | null;
	brandSource: BrandSource | null;
}

/** N° de série saisi : connu du parc installé ou non. */
export interface SearchSerial {
	value: string;
	known: boolean;
	/** Vues éclatées et garantie (/sav/<n°>), seulement si le n° est connu. */
	savUrl: string | null;
	/** Codes des produits finis de l'appareil. */
	productCodes: string[];
	/** La référence saisie diffère des modèles de l'appareil. */
	conflict: boolean;
}

/** Produit du catalogue de la référence (fiche /produit/<slug>). */
export interface SearchProduct {
	code: string;
	name: string;
	slug: string | null;
}

export interface ManualDocument {
	id: string;
	title: string;
	type: ManualType;
	typeLabel: string;
	/** Codes ISO 639-1, français d'abord. */
	languages: string[];
	/** PDF officiel, en https (sinon pas de lien). */
	url: string;
	official: boolean;
	manualNumber: string | null;
	year: string | null;
	pages: number | null;
	/** Modèles listés en page de garde. */
	models: string[];
	/** Texte indexé pour les questions. */
	hasText: boolean;
}

export interface ManualGroup {
	level: Exclude<MatchLevel, 'aucun'>;
	reason: MatchReason | null;
	documents: ManualDocument[];
}

/** Produit candidat quand la saisie est un nom (« mural titane ») : le visiteur choisit son modèle. */
export interface ModelChoice {
	code: string;
	name: string;
}

export interface ManualSearch {
	query: string;
	reference: SearchReference | null;
	serial: SearchSerial | null;
	product: SearchProduct | null;
	/** Meilleur groupe non vide, `aucun` sinon. */
	level: MatchLevel;
	groups: ManualGroup[];
	choices: ModelChoice[];
	/** Au moins un document exact ou série a un texte indexé. */
	askable: boolean;
}

/** Saisie de la page : `q` libre, ou `ref`, `sn`, `marque` (après l'écran de validation). */
export interface AssistanceInput {
	q: string;
	ref: string;
	sn: string;
	marque: string;
}

/** Ce que rend la query `getManuals` : la recherche, ou le message d'un refus. */
export interface ManualsPage {
	input: AssistanceInput;
	search: ManualSearch | null;
	error: string | null;
}
