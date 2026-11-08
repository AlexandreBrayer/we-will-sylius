// Manuels et notices (/assistance) : libellés, ordre, URL, contrôle des champs et lecture défensive de la
// réponse de l'API. Module pur (aucun import de SvelteKit) : testé par `npm test` (test/assistance.test.ts).
// Les règles de correspondance (exact, série, famille) sont celles de l'API ; le front les affiche.
import type {
	AssistanceInput,
	BrandSource,
	GroupSource,
	ManualDocument,
	ManualGroup,
	ManualSearch,
	ManualType,
	MatchLevel,
	MatchReason,
	ModelChoice,
	SearchProduct,
	SearchReference,
	SearchSerial
} from './types.js';

/** Bornes de l'API : saisie libre et paramètres de recherche. */
export const QUERY_MAX = 100;
/** Bornes de l'écran de validation (référence et n° de série d'une plaque, marque). */
export const REFERENCE_MAX = 40;
export const SERIAL_MAX = 32;
export const BRAND_MAX = 60;

export const FIELD_MESSAGES = {
	required: 'Saisissez la référence ou le n° de série de l’appareil.',
	query: `Saisie trop longue : ${QUERY_MAX} caractères au plus.`,
	reference: `La référence ne doit pas dépasser ${REFERENCE_MAX} caractères.`,
	serial: `Le n° de série ne doit pas dépasser ${SERIAL_MAX} caractères.`,
	brand: `La marque ne doit pas dépasser ${BRAND_MAX} caractères.`
} as const;

/** Types de document, dans l'ordre d'affichage de l'API (ManualType). */
export const MANUAL_TYPES: readonly ManualType[] = ['installation', 'utilisation', 'technique', 'service', 'fiche_technique', 'pieces', 'telecommande', 'brochure', 'autre'];

// ── Libellés ──

const LEVEL_LABELS: Record<MatchLevel, string> = {
	exact: 'Documents de ce modèle',
	serie: 'Documents de la même série',
	famille: 'Autre série du même type d’appareil',
	aucun: 'Aucun manuel trouvé'
};

/** Titre du bandeau d'un niveau. */
export const levelLabel = (level: MatchLevel): string => LEVEL_LABELS[level];

/**
 * Explication d'un groupe selon sa raison : ce que le document couvre par rapport au modèle cherché.
 * `reference` : forme canonique cherchée, citée quand elle est connue.
 */
export function reasonLabel(reason: MatchReason | null, reference: string | null = null): string {
	switch (reason) {
		case 'reference_identique':
			return reference ? `Le modèle ${reference} figure dans la liste des modèles de ces documents.` : 'Le modèle figure dans la liste des modèles de ces documents.';
		case 'variante_suffixe':
			return 'Même modèle, autre variante : vérifiez la liste des modèles en page de garde.';
		case 'autre_puissance':
			return reference
				? `Documents de la même série, pas de ce modèle exact (${reference}) : vérifiez la puissance dans la liste des modèles.`
				: 'Documents de la même série, pas de ce modèle exact : vérifiez la puissance dans la liste des modèles.';
		case 'meme_type_appareil':
			return 'Autre série du même type d’appareil : généralités seulement, jamais pour une valeur technique.';
		default:
			return '';
	}
}

const TYPE_LABELS: Record<ManualType, string> = {
	installation: 'Manuel d’installation',
	utilisation: 'Manuel d’utilisation',
	technique: 'Manuel technique',
	service: 'Manuel de service',
	fiche_technique: 'Fiche technique',
	pieces: 'Liste des pièces',
	telecommande: 'Télécommande',
	brochure: 'Brochure',
	autre: 'Document'
};

/** Libellé d'un type de document ; celui de l'API s'il est donné. */
export const typeLabel = (type: ManualType, fromApi = ''): string => (fromApi.trim() !== '' ? fromApi.trim() : TYPE_LABELS[type]);

/** Langues en minuscules, sans doublon, le français d'abord, les autres dans l'ordre reçu. */
export function sortLanguages(languages: readonly string[]): string[] {
	const unique = [...new Set(languages.map((code) => code.trim().toLowerCase()).filter((code) => /^[a-z]{2,3}$/.test(code)))];
	return [...unique.filter((code) => code === 'fr'), ...unique.filter((code) => code !== 'fr')];
}

/** « 24 p. », rien sans nombre de pages. */
export const pagesLabel = (pages: number | null): string => (pages !== null && pages > 0 ? `${pages} p.` : '');

/** Bandeau d'alerte quand le n° de série et la référence saisie ne désignent pas le même appareil. */
export function conflictMessage(serial: SearchSerial, reference: string | null): string {
	const models = serial.productCodes.join(', ');
	return reference
		? `Le n° de série correspond à ${models}, la référence saisie est ${reference} : vérifiez la plaque.`
		: `Le n° de série correspond à ${models} : vérifiez la plaque.`;
}

/**
 * Origine des documents d'un groupe, dite quand le n° de série et la référence saisie divergent : les produits finis
 * du n° de série, ou la référence saisie (forme canonique).
 */
export function sourceLabel(source: GroupSource, serial: SearchSerial | null, reference: string | null): string {
	if (source === 'serial') {
		const models = serial?.productCodes.join(', ') ?? '';
		return `D’après le n° de série${serial ? ` ${serial.value}` : ''}${models ? ` (${models})` : ''}`;
	}
	return `D’après la référence saisie${reference ? ` ${reference}` : ''}`;
}

/** Plus de modèles correspondent que la liste n'en montre (`moreChoices`). */
export const MORE_CHOICES_MESSAGE = 'Précisez la puissance ou la référence pour affiner.';

// ── URL ──

/**
 * URL de la page : `{q}` (saisie libre) ou `{ref, sn, marque}` (après validation) ; les champs vides sont omis.
 * `q` l'emporte : une recherche libre ne garde pas les champs d'une validation.
 */
export function assistanceUrl(input: Partial<AssistanceInput>): string {
	const params = new URLSearchParams();
	const q = input.q?.trim() ?? '';
	if (q !== '') {
		params.set('q', q);
	} else {
		for (const name of ['ref', 'sn', 'marque'] as const) {
			const value = input[name]?.trim() ?? '';
			if (value !== '') params.set(name, value);
		}
	}
	const search = params.toString();
	return search === '' ? '/assistance' : `/assistance?${search}`;
}

/** Saisie lue dans l'URL de la page. `reference` et `serial` (noms de l'API) valent `ref` et `sn`. */
export function searchInput(params: Pick<URLSearchParams, 'get'>): AssistanceInput {
	const read = (...names: string[]): string => {
		for (const name of names) {
			const value = params.get(name)?.trim() ?? '';
			if (value !== '') return value;
		}
		return '';
	};
	return { q: read('q'), ref: read('ref', 'reference'), sn: read('sn', 'serial'), marque: read('marque') };
}

/** Rien n'est saisi : la page montre la barre seule. */
export const isEmptyInput = (input: AssistanceInput): boolean => input.q === '' && input.ref === '' && input.sn === '';

/**
 * Lien vers le PDF officiel, à la page `page` si elle est donnée (`#page=N`, ignoré par Safari iOS). Seul le https
 * est accepté : tout autre schéma (`javascript:`, `data:`, http) ne donne pas de lien.
 */
export function pdfPageUrl(url: string, page: number | null = null): string | null {
	let parsed: URL;
	try {
		parsed = new URL(url.trim());
	} catch {
		return null;
	}
	if (parsed.protocol !== 'https:') return null;
	parsed.hash = page !== null && Number.isInteger(page) && page >= 1 ? `page=${page}` : '';
	return parsed.toString();
}

/** Fiche produit du catalogue, null sans slug. */
export const productUrl = (product: SearchProduct): string | null => (product.slug ? `/produit/${encodeURIComponent(product.slug)}` : null);

/** Demande au support, sujet prérempli avec ce qui a été saisi (/contact?sujet=). */
export const contactUrl = (read: string): string =>
	`/contact?sujet=${encodeURIComponent(read.trim() === '' ? 'Manuels et notices : aucun document trouvé' : `Manuels et notices : aucun document pour ${read.trim()}`)}`;

// ── Contrôle des champs (navigateur et serveur ; l'API reste juge) ──

export type FieldIssues = Partial<Record<'q' | 'ref' | 'sn' | 'marque', string>>;

/**
 * Champs d'une recherche : saisie libre (100 caractères), ou référence et n° de série (l'un des deux requis ;
 * 40 et 32 caractères), marque (60). Aucun problème : objet vide.
 */
export function validateFields(input: Partial<AssistanceInput>): FieldIssues {
	const q = input.q?.trim() ?? '';
	if (q !== '') return q.length > QUERY_MAX ? { q: FIELD_MESSAGES.query } : {};

	const ref = input.ref?.trim() ?? '';
	const sn = input.sn?.trim() ?? '';
	const marque = input.marque?.trim() ?? '';
	const issues: FieldIssues = {};
	if (ref === '' && sn === '') issues.ref = FIELD_MESSAGES.required;
	if (ref.length > REFERENCE_MAX) issues.ref = FIELD_MESSAGES.reference;
	if (sn.length > SERIAL_MAX) issues.sn = FIELD_MESSAGES.serial;
	if (marque.length > BRAND_MAX) issues.marque = FIELD_MESSAGES.brand;
	return issues;
}

// ── Réponse de l'API ──

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;
const text = (value: unknown): string => (typeof value === 'string' ? value.trim() : '');
const nullableText = (value: unknown): string | null => (text(value) === '' ? null : text(value));
const texts = (value: unknown): string[] => (Array.isArray(value) ? value.map(text).filter((item) => item !== '') : []);

const LEVELS: readonly MatchLevel[] = ['exact', 'serie', 'famille', 'aucun'];
const REASONS: readonly MatchReason[] = ['reference_identique', 'variante_suffixe', 'autre_puissance', 'meme_type_appareil'];
const BRAND_SOURCES: readonly BrandSource[] = ['plaque', 'deduite', 'catalogue'];
const SOURCES: readonly GroupSource[] = ['reference', 'serial'];

const pick = <T extends string>(values: readonly T[], value: unknown): T | null => (values as readonly unknown[]).includes(value) ? (value as T) : null;

function toDocument(raw: unknown): ManualDocument | null {
	if (!isRecord(raw)) return null;
	const id = text(raw.id);
	const title = text(raw.title);
	const url = text(raw.url);
	if (id === '' || title === '' || pdfPageUrl(url) === null) return null;
	const type = pick(MANUAL_TYPES, raw.type) ?? 'autre';
	const pages = Number(raw.pages);
	const year = typeof raw.year === 'number' ? String(raw.year) : nullableText(raw.year);

	return {
		id,
		title,
		type,
		typeLabel: typeLabel(type, text(raw.typeLabel)),
		languages: sortLanguages(texts(raw.languages)),
		url,
		official: raw.official === true,
		manualNumber: nullableText(raw.manualNumber),
		year,
		pages: Number.isInteger(pages) && pages > 0 ? pages : null,
		models: texts(raw.models),
		hasText: raw.hasText === true
	};
}

/** Un groupe sans niveau ni source reconnus est écarté : ses documents ne peuvent pas être attribués. */
function toGroup(raw: unknown): ManualGroup | null {
	if (!isRecord(raw)) return null;
	const level = pick(LEVELS, raw.level);
	const source = pick(SOURCES, raw.source);
	if (level === null || level === 'aucun' || source === null) return null;
	const documents = (Array.isArray(raw.documents) ? raw.documents : []).map(toDocument).filter((doc): doc is ManualDocument => doc !== null);
	return documents.length > 0 ? { level, reason: pick(REASONS, raw.reason), source, documents } : null;
}

function toReference(raw: unknown): SearchReference | null {
	if (!isRecord(raw) || text(raw.canonical) === '') return null;
	return {
		read: text(raw.read),
		canonical: text(raw.canonical),
		structured: raw.structured === true,
		corrected: raw.corrected === true,
		brand: nullableText(raw.brand),
		brandSource: pick(BRAND_SOURCES, raw.brandSource)
	};
}

function toSerial(raw: unknown): SearchSerial | null {
	if (!isRecord(raw) || text(raw.value) === '') return null;
	const known = raw.known === true;
	const savUrl = text(raw.savUrl);
	return {
		value: text(raw.value),
		known,
		// Lien interne seulement (/sav/<n°>).
		savUrl: known && savUrl.startsWith('/sav/') ? savUrl : null,
		productCodes: texts(raw.productCodes),
		conflict: raw.conflict === true
	};
}

function toProduct(raw: unknown): SearchProduct | null {
	if (!isRecord(raw) || text(raw.code) === '') return null;
	return { code: text(raw.code), name: text(raw.name) || text(raw.code), slug: nullableText(raw.slug) };
}

function toChoice(raw: unknown): ModelChoice | null {
	if (!isRecord(raw) || text(raw.code) === '') return null;
	return { code: text(raw.code), name: text(raw.name) || text(raw.code) };
}

/**
 * Réponse de l'API → recherche affichable. Un document sans id, titre ou URL https est écarté ; un groupe vide ou
 * sans source aussi. Le niveau d'ensemble est celui de l'API, ramené à `aucun` s'il ne reste aucun groupe.
 */
export function toManualSearch(raw: unknown): ManualSearch {
	const body = isRecord(raw) ? raw : {};
	const groups = (Array.isArray(body.groups) ? body.groups : []).map(toGroup).filter((group): group is ManualGroup => group !== null);
	const level = pick(LEVELS, body.level) ?? 'aucun';
	const choices = (Array.isArray(body.choices) ? body.choices : []).map(toChoice).filter((choice): choice is ModelChoice => choice !== null);

	return {
		query: text(body.query),
		reference: toReference(body.reference),
		serial: toSerial(body.serial),
		product: toProduct(body.product),
		level: groups.some((group) => group.level === level) ? level : (groups[0]?.level ?? 'aucun'),
		groups,
		choices,
		moreChoices: body.moreChoices === true && choices.length > 0,
		askable: body.askable === true && groups.length > 0
	};
}
