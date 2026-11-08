// Manuels et notices (/assistance) : formes de GET /api/v2/shop/yack/assistance/manuals, POST …/plate-readings et
// POST …/questions (docs/API_FRONT.md, section 13). Le front les reçoit par `toManualSearch`, `toPlateReading` et
// `toManualAnswer` (#lib/assistance/assistance.ts), qui écartent ce qui est mal formé.

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
	/** Pages du PDF indexées (« 4-8, 10, 33-38 »), null si l'API ne les donne pas. */
	indexedPages: string | null;
}

/**
 * D'où viennent les documents d'un groupe : la référence saisie, ou les produits finis du n° de série. Les deux
 * coexistent seulement en cas de conflit (`SearchSerial.conflict`).
 */
export type GroupSource = 'reference' | 'serial';

export interface ManualGroup {
	level: Exclude<MatchLevel, 'aucun'>;
	reason: MatchReason | null;
	source: GroupSource;
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
	/** De 1 à 8 modèles, avec `groups` vide. */
	choices: ModelChoice[];
	/** D'autres produits correspondent au-delà des choix rendus : la saisie est à préciser. */
	moreChoices: boolean;
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

/**
 * Champ de la barre de recherche. Après l'écran de validation (`ref` et `sn`), le champ visible est la référence et
 * le n° de série suit en champ caché : relancer la recherche le garde. Sinon, saisie libre (`q`).
 */
export interface SearchForm {
	name: 'q' | 'ref';
	value: string;
	/** N° de série conservé (champ caché `sn`), null sinon. */
	serial: string | null;
	maxlength: number;
}

/** Ce que rend la query `getManuals` : la recherche, ou le message d'un refus. */
export interface ManualsPage {
	input: AssistanceInput;
	search: ManualSearch | null;
	error: string | null;
}

// ── Photo de la plaque et écran « C'est bien ça ? » ──

/** Origine de la marque lue sur une photo : imprimée sur la plaque, ou déduite de la référence. */
export type PlateBrandSource = Exclude<BrandSource, 'catalogue'>;

/** POST /api/v2/shop/yack/assistance/plate-readings : ce qui est lu sur la plaque, sans aucune recherche. */
export interface PlateReading {
	serialNumber: string | null;
	/** Référence telle que lue (SRK2SZS-W). */
	referenceRead: string | null;
	/** Forme canonique (SRK25ZS-W), null si rien d'exploitable n'est lu. */
	reference: string | null;
	/** Désignation lue, en clair. */
	model: string | null;
	brand: string | null;
	brandSource: PlateBrandSource | null;
	simulated: boolean;
}

/**
 * Ce qui ouvre l'écran de validation : une lecture (`read`), une plaque illisible, une lecture indisponible (503), ou
 * la validation renvoyée vide sans JavaScript (`retry` : l'écran revient avec le message sur le champ).
 */
export type ValidationStatus = 'read' | 'unreadable' | 'unavailable' | 'retry';

/** Champs de l'écran de validation, noms de l'URL des résultats (`?ref=&sn=&marque=`). */
export interface PlateValues {
	ref: string;
	sn: string;
	marque: string;
}

/** Écran « C'est bien ça ? » : valeurs à vérifier ou corriger, et d'où elles viennent. */
export interface PlateCheck {
	status: ValidationStatus;
	/** « Plaque illisible : saisissez la référence » ou « Lecture indisponible : saisissez la référence ». */
	message: string | null;
	/** Valeurs préremplies. */
	values: PlateValues;
	/**
	 * Origine de chaque valeur, à côté de son champ : « lue sur la plaque », « déduite de la référence »,
	 * « lu : SRK2SZS-W, compris : SRK25ZS-W » ; vide si rien n'est lu.
	 */
	notes: PlateValues;
	/** Désignation lue, affichée en texte. */
	model: string | null;
	simulated: boolean;
}

// ── Question aux manuels indexés (« Une question sur ce modèle ? ») ──

/** Page d'un manuel citée par une réponse : lien vers le PDF officiel à cette page (`#page=N`). */
export interface ManualSource {
	documentId: string;
	title: string;
	page: number;
	/** PDF en https, à la page (`…pdf#page=47`). */
	url: string;
}

/** POST /api/v2/shop/yack/assistance/questions (comptes connectés). */
export interface ManualAnswer {
	/** Texte brut, affiché en texte (jamais en HTML). */
	answer: string;
	sources: ManualSource[];
	/** Rédigée par l'adapter de démonstration : repère « démonstration ». */
	simulated: boolean;
}

/**
 * Ce que la question interroge : la référence envoyée à l'API, et la source des groupes dont les documents indexés
 * répondent (la référence saisie, ou les produits finis du n° de série).
 */
export interface QuestionTarget {
	reference: string;
	source: GroupSource;
}

/** Manuel dont le texte est indexé, et ses pages (« Pages indexées : … » sous le panneau). */
export interface IndexedManual {
	id: string;
	title: string;
	pages: string | null;
}
