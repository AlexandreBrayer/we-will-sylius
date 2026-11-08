// Style of a CMS block (D6): same properties, keys and formats as App\Yack\Content\Domain\BlockStyle
// and Charte (checked by test/style.test.ts, which reads those PHP files and _tokens.scss).
// Sylius validates the style before saving; this module validates it again before rendering
// (defence in depth): a value outside its format is dropped, never written into the page.
// Nothing but `--yb-*` custom properties built here enters the `style` attribute.

/** Style properties, in the order of BlockStyle::PROPERTIES. */
export const STYLE_PROPERTIES = [
	'fond',
	'texte',
	'accent',
	'titre',
	'hautEspace',
	'basEspace',
	'largeur',
	'alignement',
	'arrondi',
	'masquerMobile',
	'masquerOrdinateur',
	'ancre'
] as const;

export type StyleProperty = (typeof STYLE_PROPERTIES)[number];

/** Normalized style of a block: property => value; an absent property is not set. */
export type BlockStyle = Partial<Record<StyleProperty, string>>;

/** Keys of Charte::COLORS; each is the `$yack-<key>` token, exposed as `--yack-<key>` (_cms-blocks.scss). */
export const CHARTE_COLORS = [
	'magenta',
	'magenta-dark',
	'plum',
	'teal',
	'teal-dark',
	'teal-deep',
	'yellow',
	'ochre',
	'dark',
	'gray',
	'light',
	'white',
	'black'
] as const;

/** Keys of Charte::TITLE_SIZES, exposed as `--yack-title-<key>`. */
export const CHARTE_TITLE_SIZES = ['s', 'm', 'l', 'xl'] as const;

/** Keys of Charte::SPACINGS, exposed as `--yack-space-<key>`. */
export const CHARTE_SPACINGS = ['0', 's', 'm', 'l', 'xl'] as const;

/** Keys of Charte::RADII, exposed as `--yack-radius-<key>`. */
export const CHARTE_RADII = ['aucun', 'petit', 'moyen', 'grand'] as const;

/** BlockStyle::WIDTHS, ALIGNMENTS, YES_NO. */
export const STYLE_WIDTHS = ['contenu', 'large', 'pleine'] as const;
export const STYLE_ALIGNMENTS = ['gauche', 'centre', 'droite'] as const;
export const STYLE_YES_NO = ['oui', 'non'] as const;

/** Bounds of the free values, in px (BlockStyle::TITLE_MIN / MAX, SPACING_MIN / MAX). */
export const TITLE_MIN = 14;
export const TITLE_MAX = 96;
export const SPACING_MIN = 0;
export const SPACING_MAX = 200;

/** BlockStyle::COLOR_PATTERN, PX_PATTERN, ANCHOR_PATTERN (JavaScript `$` never accepts a trailing newline). */
export const COLOR_PATTERN = /^#[0-9a-fA-F]{6}$/;
export const PX_PATTERN = /^(0|[1-9]\d{0,2})px$/;
export const ANCHOR_PATTERN = /^[a-z0-9-]{1,40}$/;

const has = (list: readonly string[], value: string): boolean => list.includes(value);

/**
 * Style of a block as the API sends it: an object, `[]` when empty (PHP empty array), absent
 * for a block saved before the style existed. Anything else gives an empty style; unknown
 * properties and non string values are dropped (their format is checked by styleToCss).
 */
export function readStyle(value: unknown): BlockStyle {
	if (typeof value !== 'object' || value === null || Array.isArray(value)) return {};
	const source = value as Record<string, unknown>;
	const style: BlockStyle = {};
	for (const property of STYLE_PROPERTIES) {
		const raw = source[property];
		if (typeof raw === 'string' && raw !== '') style[property] = raw;
	}
	return style;
}

function px(value: string, min: number, max: number): string | null {
	const match = PX_PATTERN.exec(value);
	if (!match) return null;
	const size = Number(match[1]);
	return size >= min && size <= max ? value : null;
}

/** Colour: a charte key gives its custom property, a free #rrggbb passes as is (lower case). */
function color(value: string): string | null {
	if (has(CHARTE_COLORS, value)) return `var(--yack-${value})`;
	return COLOR_PATTERN.test(value) ? value.toLowerCase() : null;
}

function titleSize(value: string): string | null {
	if (has(CHARTE_TITLE_SIZES, value)) return `var(--yack-title-${value})`;
	return px(value, TITLE_MIN, TITLE_MAX);
}

function spacing(value: string): string | null {
	if (has(CHARTE_SPACINGS, value)) return `var(--yack-space-${value})`;
	return px(value, SPACING_MIN, SPACING_MAX);
}

/** Wrapper of a styled block: `section.yack-cms-block` (BlockRenderer). */
export interface BlockCss {
	/** `yack-cms-block`, then one modifier per property in effect. */
	class: string;
	/** Custom properties `--yb-*` => validated value. */
	vars: Record<string, string>;
	/** `style` attribute: the custom properties only. */
	style: string;
	/** Anchor (`id` of the wrapper), null when none. */
	id: string | null;
}

/**
 * Classes and custom properties of a block style (D6). Colours, title size, spacings and
 * radius become `--yb-*` custom properties, plus a modifier class for the ones read by the
 * descendants (`--fond`, `--texte`, `--accent`, `--titre`, `--arrondi`); width, alignment and
 * hiding become classes; the anchor becomes the id. A value outside its format is ignored.
 * null when nothing applies: the block is rendered without wrapper, exactly as without style.
 */
export function styleToCss(value: unknown): BlockCss | null {
	const style = readStyle(value);
	const classes: string[] = [];
	const vars: Record<string, string> = {};
	const set = (name: string, modifier: string | null, css: string | null) => {
		if (css === null) return;
		vars[name] = css;
		if (modifier) classes.push(`yack-cms-block--${modifier}`);
	};

	if (style.fond) set('--yb-bg', 'fond', color(style.fond));
	if (style.texte) set('--yb-fg', 'texte', color(style.texte));
	if (style.accent) set('--yb-accent', 'accent', color(style.accent));
	if (style.titre) set('--yb-title-size', 'titre', titleSize(style.titre));
	if (style.hautEspace) set('--yb-pad-top', null, spacing(style.hautEspace));
	if (style.basEspace) set('--yb-pad-bottom', null, spacing(style.basEspace));
	if (style.arrondi && has(CHARTE_RADII, style.arrondi)) set('--yb-radius', 'arrondi', `var(--yack-radius-${style.arrondi})`);
	if (style.largeur && has(STYLE_WIDTHS, style.largeur)) classes.push(`yack-cms-block--width-${style.largeur}`);
	if (style.alignement && has(STYLE_ALIGNMENTS, style.alignement)) classes.push(`yack-cms-block--align-${style.alignement}`);
	if (style.masquerMobile === 'oui') classes.push('yack-cms-block--hide-mobile');
	if (style.masquerOrdinateur === 'oui') classes.push('yack-cms-block--hide-desktop');
	const id = style.ancre && ANCHOR_PATTERN.test(style.ancre) ? style.ancre : null;

	const names = Object.keys(vars);
	if (classes.length === 0 && names.length === 0 && id === null) return null;
	return {
		class: ['yack-cms-block', ...classes].join(' '),
		vars,
		style: names.map((name) => `${name}: ${vars[name]}`).join('; '),
		id
	};
}
