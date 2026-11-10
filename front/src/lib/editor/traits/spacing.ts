// Espacement d'un bloc (E4) : un seul champ du panneau (YackSpacing.svelte) pour les quatre propriétés du style
// `hautEspace`, `droiteEspace`, `basEspace`, `gaucheEspace` (Content\Domain\BlockStyle). Le format enregistré ne
// change pas : chaque côté reste un attribut `yb-<propriété>` du composant (panels/blockStyle.ts), une clé des pas de
// la charte (`0`, `s`, `m`, `l`, `xl`) ou une valeur libre en px (comptes autorisés), '' quand rien n'est posé.
// Module pur, testé par test/spacing.test.ts.
import { normalizeFreeValue, styleAttribute, type Charte, type FreeValue } from '../panels/blockStyle.ts';
import { CHARTE_SPACINGS, type StyleProperty } from '../../blocks/style.ts';

export type SpacingProperty = Extract<StyleProperty, 'hautEspace' | 'droiteEspace' | 'basEspace' | 'gaucheEspace'>;

export interface SpacingSide {
	property: SpacingProperty;
	/** Nom du champ (lecteur d'écran, message hors charte). */
	label: string;
	/** Côté dans les messages (« 120px en haut »). */
	where: string;
}

/** Les quatre côtés, dans l'ordre de la maquette (haut, droite, bas, gauche : sens des aiguilles d'une montre). */
export const SPACING_SIDES: readonly SpacingSide[] = [
	{ property: 'hautEspace', label: 'Espace en haut', where: 'en haut' },
	{ property: 'droiteEspace', label: 'Espace à droite', where: 'à droite' },
	{ property: 'basEspace', label: 'Espace en bas', where: 'en bas' },
	{ property: 'gaucheEspace', label: 'Espace à gauche', where: 'à gauche' }
];

export type SpacingValues = Record<SpacingProperty, string>;

/** Valeurs des quatre côtés lues dans les attributs du composant ('' : rien de posé). */
export function readSpacing(attributes: Record<string, unknown>): SpacingValues {
	const values = {} as SpacingValues;
	for (const { property } of SPACING_SIDES) {
		const raw = attributes[styleAttribute(property)];
		values[property] = typeof raw === 'string' ? raw : '';
	}
	return values;
}

/**
 * Les côtés sont liés à l'ouverture quand ils ont tous la même valeur (rien de posé compris) : sinon une saisie
 * liée écraserait trois valeurs différentes, le cadenas s'ouvre donc tout seul.
 */
export function sidesAgree(values: SpacingValues): boolean {
	const [first, ...others] = SPACING_SIDES.map(({ property }) => values[property]);
	return others.every((value) => value === first);
}

/** Côtés touchés par une saisie ou par le menu des pas : les quatre quand ils sont liés, sinon celui choisi. */
export function targetSides(linked: boolean, side: SpacingProperty): SpacingProperty[] {
	return linked ? SPACING_SIDES.map(({ property }) => property) : [side];
}

/**
 * Attributs à poser pour mettre `value` sur `sides` (un seul appel à `addAttributes` : un seul pas d'annulation),
 * null quand rien ne change.
 */
export function spacingChanges(values: SpacingValues, sides: readonly SpacingProperty[], value: string): Record<string, string> | null {
	const changes: Record<string, string> = {};
	for (const side of sides) {
		if (values[side] !== value) changes[styleAttribute(side)] = value;
	}
	return Object.keys(changes).length > 0 ? changes : null;
}

/** Valeur commune aux côtés `sides`, null quand ils diffèrent (le menu des pas ne coche alors rien). */
export function commonValue(values: SpacingValues, sides: readonly SpacingProperty[]): string | null {
	const [first, ...others] = sides.map((side) => values[side]);
	return first !== undefined && others.every((value) => value === first) ? first : null;
}

/** Une clé des pas de la charte. */
export const isSpacingStep = (value: string): boolean => (CHARTE_SPACINGS as readonly string[]).includes(value);

/** Texte d'un champ : la clé en capitales (`M`, `0`), une valeur libre telle quelle (`120px`), '' quand rien n'est posé. */
export function spacingText(value: string): string {
	return isSpacingStep(value) ? value.toUpperCase() : value;
}

/**
 * Saisie d'un champ : vide = rien de posé ; une clé des pas, sans tenir compte de la casse (`m`, `XL`) ; sinon un
 * nombre de px (`40`, `40px`) borné comme `hautEspace`, une valeur égale à un pas devenant sa clé (normalizeFreeValue).
 * Les px ne sont acceptés que si le compte peut sortir de la charte (`free`).
 */
export function parseSpacing(input: string, charte: Charte, free: boolean): FreeValue {
	const raw = input.trim().toLowerCase();
	if (raw === '') return { ok: true, value: '' };
	if (isSpacingStep(raw)) return { ok: true, value: raw };
	if (!free) return { ok: false, message: 'Choisissez un pas de la charte : 0, S, M, L ou XL.' };
	const result = normalizeFreeValue('spacing', raw, charte);
	return result.ok ? result : { ok: false, message: `Un pas de la charte (0, S, M, L, XL) ou ${result.message.charAt(0).toLowerCase()}${result.message.slice(1)}` };
}

/** Côtés dont la valeur est hors charte (px libre), dans l'ordre de SPACING_SIDES. */
export function offCharteSides(values: SpacingValues): SpacingSide[] {
	return SPACING_SIDES.filter(({ property }) => values[property] !== '' && !isSpacingStep(values[property]));
}
