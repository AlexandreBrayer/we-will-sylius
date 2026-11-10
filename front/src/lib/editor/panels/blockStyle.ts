// Style of a block in the builder (D9, D10): where it lives in the GrapesJS model, how a value typed by
// the editor becomes a value of BlockStyle, and what the account may choose. Pure module (no GrapesJS,
// no Svelte), tested by test/block-style.test.ts. Relative `.ts` import: run by node in the tests.
// Sylius stays the judge (BlockStyle::normalize, CharteGuard): this module only anticipates it.
import {
	ANCHOR_PATTERN,
	CHARTE_COLORS,
	CHARTE_SPACINGS,
	CHARTE_TITLE_SIZES,
	COLOR_PATTERN,
	SPACING_MAX,
	SPACING_MIN,
	STYLE_PROPERTIES,
	TITLE_MAX,
	TITLE_MIN,
	readStyle,
	type BlockStyle,
	type StyleProperty
} from '../../blocks/style.ts';

/** Content\Domain\BuilderLevel. */
export type BuilderLevel = 'charte' | 'libre_avertissement' | 'libre';

export interface CharteEntry {
	key: string;
	label: string;
	value: string;
}

export interface Charte {
	colors: CharteEntry[];
	titleSizes: CharteEntry[];
	spacings: CharteEntry[];
	radii: CharteEntry[];
}

/** GET /api/v2/admin/yack/builder/me. */
export interface BuilderMe {
	level: BuilderLevel;
	levelLabel: string;
	firstName: string;
	charte: Charte;
}

const LEVELS: readonly BuilderLevel[] = ['charte', 'libre_avertissement', 'libre'];

/** Free values (#rrggbb, px) allowed: libre_avertissement and libre (BuilderLevel::allowsFreeValues). */
export const allowsFreeValues = (level: BuilderLevel): boolean => level !== 'charte';

/** « Hors charte » warning under the field: libre_avertissement only (BuilderLevel::warnsOnFreeValues). */
export const warnsOnFreeValues = (level: BuilderLevel): boolean => level === 'libre_avertissement';

function entries(value: unknown): CharteEntry[] {
	if (!Array.isArray(value)) return [];
	return value.flatMap((entry: unknown) => {
		if (typeof entry !== 'object' || entry === null) return [];
		const { key, label, value: css } = entry as Record<string, unknown>;
		return typeof key === 'string' && typeof label === 'string' && typeof css === 'string' ? [{ key, label, value: css }] : [];
	});
}

/**
 * Answer of /builder/me as the builder uses it. Anything unexpected falls back on the strictest
 * reading: level `charte`, entries dropped. A colour is kept only with a #rrggbb value (it is written
 * into the swatch's `style`).
 */
export function parseBuilderMe(raw: unknown): BuilderMe {
	const source = typeof raw === 'object' && raw !== null ? (raw as Record<string, unknown>) : {};
	const level = LEVELS.find((candidate) => candidate === source.level) ?? 'charte';
	const charte = typeof source.charte === 'object' && source.charte !== null ? (source.charte as Record<string, unknown>) : {};
	return {
		level,
		levelLabel: typeof source.levelLabel === 'string' && source.levelLabel !== '' ? source.levelLabel : LEVEL_LABELS[level],
		firstName: typeof source.firstName === 'string' ? source.firstName : '',
		charte: {
			colors: entries(charte.colors).filter((entry) => COLOR_PATTERN.test(entry.value)),
			titleSizes: entries(charte.titleSizes),
			spacings: entries(charte.spacings),
			radii: entries(charte.radii)
		}
	};
}

/** BuilderLevel::label(), when the API gives none. */
export const LEVEL_LABELS: Record<BuilderLevel, string> = {
	charte: 'Charte',
	libre_avertissement: 'Libre avec avertissement',
	libre: 'Libre'
};

// ── The style in the GrapesJS model ──

/**
 * A style property lives in the attributes of the block's component, as `yb-<property>`, next to
 * its props: traits, undo (Ctrl+Z), re-render of the canvas and the project data (grapesData) treat
 * it like a prop, and `pickProps` never sends it as a prop.
 */
export const STYLE_ATTRIBUTE_PREFIX = 'yb-';

export const styleAttribute = (property: StyleProperty): string => `${STYLE_ATTRIBUTE_PREFIX}${property}`;

/** Style of a block from its component attributes (empty value: not set). */
export function pickStyle(attributes: Record<string, unknown>): BlockStyle {
	const raw: Record<string, unknown> = {};
	for (const property of STYLE_PROPERTIES) raw[property] = attributes[styleAttribute(property)];
	return readStyle(raw);
}

/** Attributes of a style read from the API (`[]`, absent or invalid: none). */
export function styleAttributes(style: unknown): Record<string, string> {
	const attributes: Record<string, string> = {};
	for (const [property, value] of Object.entries(readStyle(style))) attributes[styleAttribute(property as StyleProperty)] = value;
	return attributes;
}

/**
 * Attributes to set so that a component carrying `attributes` gets exactly `style` ('' clears a
 * property); null when it already has it.
 */
export function styleAttributeChanges(attributes: Record<string, unknown>, style: unknown): Record<string, string> | null {
	const current = pickStyle(attributes);
	const wanted = readStyle(style);
	const changes: Record<string, string> = {};
	for (const property of STYLE_PROPERTIES) {
		if ((current[property] ?? '') !== (wanted[property] ?? '')) changes[styleAttribute(property)] = wanted[property] ?? '';
	}
	return Object.keys(changes).length > 0 ? changes : null;
}

// ── Values ──

/** Kind of a value that may be free (D5). */
export type FreeKind = 'color' | 'title' | 'spacing';

export const FREE_KIND: Partial<Record<StyleProperty, FreeKind>> = {
	fond: 'color',
	texte: 'color',
	accent: 'color',
	titre: 'title',
	hautEspace: 'spacing',
	basEspace: 'spacing',
	gaucheEspace: 'spacing',
	droiteEspace: 'spacing'
};

const CHARTE_KEYS: Record<FreeKind, readonly string[]> = {
	color: CHARTE_COLORS,
	title: CHARTE_TITLE_SIZES,
	spacing: CHARTE_SPACINGS
};

export const PX_BOUNDS: Record<Exclude<FreeKind, 'color'>, { min: number; max: number }> = {
	title: { min: TITLE_MIN, max: TITLE_MAX },
	spacing: { min: SPACING_MIN, max: SPACING_MAX }
};

const charteFamily = (charte: Charte, kind: FreeKind): CharteEntry[] =>
	kind === 'color' ? charte.colors : kind === 'title' ? charte.titleSizes : charte.spacings;

/** The value is a free one (not a key of the charte) for a property that accepts free values. */
export function isFreeValue(property: StyleProperty, value: string | undefined): boolean {
	const kind = FREE_KIND[property];
	return kind !== undefined && value !== undefined && value !== '' && !CHARTE_KEYS[kind].includes(value);
}

/** Properties of `style` holding a free value, in the order of BlockStyle::PROPERTIES. */
export function freeProperties(style: BlockStyle): StyleProperty[] {
	return STYLE_PROPERTIES.filter((property) => isFreeValue(property, style[property]));
}

/** French label of each property (BlockStyle::PROPERTIES). */
export const STYLE_LABELS: Record<StyleProperty, string> = {
	fond: 'Fond',
	texte: 'Texte',
	accent: 'Accent',
	balise: 'Balise',
	titre: 'Taille du titre',
	hautEspace: 'Espace au-dessus',
	basEspace: 'Espace en dessous',
	gaucheEspace: 'Espace à gauche',
	droiteEspace: 'Espace à droite',
	largeur: 'Largeur',
	alignement: 'Alignement',
	alignementVertical: 'Alignement vertical',
	arrondi: 'Arrondi',
	masquerMobile: 'Masquer sur mobile',
	masquerOrdinateur: 'Masquer sur ordinateur',
	ancre: 'Ancre'
};

/** « Fond #ff7700, Taille du titre 60px »: the free values of a block (tree marker), '' when none. */
export function describeFreeValues(style: BlockStyle): string {
	return freeProperties(style)
		.map((property) => `${STYLE_LABELS[property]} ${style[property]}`)
		.join(', ');
}

export type FreeValue = { ok: true; value: string } | { ok: false; message: string };

/**
 * Value typed in a free field, as BlockStyle stores it: a charte key stays a key; `#RRGGBB` goes to
 * lower case; `40` or `40px` gives `40px` within the bounds; a free value equal to a value of the
 * charte becomes its key (`#82CEC8` gives `teal`, `32px` gives `m`, `0px` gives `0`), as Sylius does,
 * so the field shows the swatch at once and the saved value is the one sent.
 */
export function normalizeFreeValue(kind: FreeKind, input: string, charte: Charte): FreeValue {
	const raw = input.trim();
	const family = charteFamily(charte, kind);
	if (CHARTE_KEYS[kind].includes(raw)) return { ok: true, value: raw };
	let value: string;
	if (kind === 'color') {
		if (!COLOR_PATTERN.test(raw)) return { ok: false, message: 'Couleur attendue au format #rrggbb (par exemple #ff7700).' };
		value = raw.toLowerCase();
	} else {
		const { min, max } = PX_BOUNDS[kind];
		const match = /^(\d{1,3})(px)?$/.exec(raw);
		const size = match ? Number(match[1]) : NaN;
		if (!Number.isInteger(size) || size < min || size > max) return { ok: false, message: `Nombre entier de ${min} à ${max} px attendu.` };
		value = `${size}px`;
	}
	const same = family.find((entry) => entry.value.toLowerCase() === value);
	return { ok: true, value: same && CHARTE_KEYS[kind].includes(same.key) ? same.key : value };
}

/** Anchor typed in the Avancé tab: lower case, then ANCHOR_PATTERN; '' removes it. */
export function normalizeAnchor(input: string, taken: readonly string[] = []): FreeValue {
	const value = input.trim().toLowerCase();
	if (value === '') return { ok: true, value };
	if (!ANCHOR_PATTERN.test(value)) return { ok: false, message: 'Lettres minuscules sans accent, chiffres et tirets, 40 caractères au plus.' };
	if (RESERVED_ANCHORS.includes(value)) return { ok: false, message: 'Cette ancre est déjà utilisée par le site : choisissez-en une autre.' };
	if (taken.includes(value)) return { ok: false, message: 'Un autre bloc de la page a déjà cette ancre.' };
	return { ok: true, value };
}

/** `id` of the shop layout (#lib/layout) an anchor must not take. */
export const RESERVED_ANCHORS: readonly string[] = ['comptes-demo', 'comptes-demo-titre', 'yack-demo-news', 'yack-header-search', 'yack-minicart-title', 'yack-nav'];
