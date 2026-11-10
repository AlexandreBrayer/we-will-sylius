// Gestures on the selected block of the builder canvas (B8-5), pure rules tested by test/selection.test.ts; the
// GrapesJS glue is in grapesSetup.ts (installSelectionGestures), the tabs in panels/BlockPanel.svelte, the
// confirmation in BuilderShell.svelte.
//  - Clicks: a click on a block opens « Contenu »; a click on the block already selected switches between « Contenu »
//    and « Style ». A double click (typing a text in place, inline/textEditing.ts) switches nothing more than its
//    first click: the clicks after the first one of a double click (`detail` 2 and more) count for nothing, and the
//    first click on a text of the selected block waits DOUBLE_CLICK_WAIT before switching (a double click cancels
//    it: the text is typed with « Contenu » open, where its field is). A click in a text being typed switches nothing.
//  - Keys, on the canvas (or nothing focused): Échap deselects, Suppr or Retour arrière asks to remove the selected
//    block (then removes it, Ctrl+Z puts it back). Never with a modifier, in a field, while a modal window is open
//    (even with the focus left on the page behind it), nor while a text is typed (its own keys: Échap ends the
//    typing first).
//  - Marks: the four spacings of the selected block, read only, hidden when none is set.
import type { TraitTab } from '#lib/blocks/registry.js';
import type { BlockStyle } from '#lib/blocks/style.js';

/** Tab of the block panel after a click on a block: Contenu for another block, Contenu ⇄ Style on the same one. */
export function tabAfterClick(current: TraitTab, sameBlock: boolean): TraitTab {
	if (!sameBlock) return 'contenu';
	return current === 'contenu' ? 'style' : 'contenu';
}

/** Wait before the first click on a text of the selected block switches the tab (a double click cancels it). */
export const DOUBLE_CLICK_WAIT = 300;

export interface CanvasClick {
	/** Mouse button (0: main). */
	button: number;
	/** Clicks in a row (`MouseEvent.detail`): 2 for the second click of a double click. */
	detail: number;
	/** The selected block was the same before the click (and is still selected). */
	sameBlock: boolean;
	/** The click lands in a text editable in place (`data-yack-edit`), not being typed. */
	onText: boolean;
	/** The click lands in a text being typed (`contenteditable`). */
	inTypedText: boolean;
}

/**
 * What a click in the canvas does to the tabs: `open` Contenu (another block), `switch` now, `switch-later` (after
 * DOUBLE_CLICK_WAIT, unless a double click comes), or nothing.
 */
export function clickGesture(click: CanvasClick): 'open' | 'switch' | 'switch-later' | 'none' {
	if (click.button !== 0 || click.inTypedText) return 'none';
	if (!click.sameBlock) return 'open';
	if (click.detail >= 2) return 'none';
	return click.onText ? 'switch-later' : 'switch';
}

export interface CanvasKey {
	key: string;
	ctrlKey: boolean;
	metaKey: boolean;
	altKey: boolean;
	shiftKey: boolean;
	/** Where the key was typed: the page of the canvas, the builder's page with nothing focused (body), elsewhere. */
	place: 'canvas' | 'body' | 'elsewhere';
	/** In a field (input, textarea, select) or a text being typed (contenteditable). */
	inField: boolean;
	/** A block (or a layout part) is selected. */
	selected: boolean;
	/** A modal window of the builder is open (confirmation, media library): its keys are its own. */
	modalOpen: boolean;
}

/** Action of a key on the selected block: deselect (Échap), remove (Suppr, Retour arrière), or none. */
export function keyAction(event: CanvasKey): 'deselect' | 'remove' | null {
	if (!event.selected || event.modalOpen || event.inField || event.place === 'elsewhere') return null;
	if (event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return null;
	if (event.key === 'Escape') return 'deselect';
	if (event.key === 'Delete' || event.key === 'Backspace') return 'remove';
	return null;
}

/** Question of the confirmation before removing a block (`inner`: blocks it holds, a section or colonnes). */
export function removeQuestion(name: string, detail: string, inner: number): { title: string; text: string } {
	const named = detail === '' ? `« ${name} »` : `« ${name} » (${detail})`;
	const held = inner === 0 ? '' : inner === 1 ? ' et le bloc qu’il contient' : ` et les ${inner} blocs qu’il contient`;
	return {
		title: 'Supprimer ce bloc ?',
		text: `Le bloc ${named}${held} ${inner === 0 ? 'sera retiré' : 'seront retirés'} de la page. Ctrl+Z (⌘+Z sur Mac) ${inner === 0 ? 'le' : 'les'} rétablit.`
	};
}

/** Spacing properties in the order of the marks: top, right, bottom, left (inner corners, clockwise from top left). */
export const SPACING_SIDES = [
	{ property: 'hautEspace', side: 'top', arrow: '↑' },
	{ property: 'droiteEspace', side: 'right', arrow: '→' },
	{ property: 'basEspace', side: 'bottom', arrow: '↓' },
	{ property: 'gaucheEspace', side: 'left', arrow: '←' }
] as const;

export type SpacingSide = (typeof SPACING_SIDES)[number]['side'];

/** Text of a spacing value: a step of the charte in capitals (« M »), a free value in px (« 24 px »), « défaut ». */
export function spacingText(value: string | undefined): string {
	if (value === undefined || value === '') return 'défaut';
	const free = /^(\d+(?:\.\d+)?)px$/.exec(value);
	return free ? `${free[1]} px` : value.toUpperCase();
}

/** Marks of the four spacings of a block style (« ↑ M »…), null when none is set (all « Par défaut »). */
export function spacingMarks(style: BlockStyle): Record<SpacingSide, string> | null {
	if (SPACING_SIDES.every(({ property }) => !style[property])) return null;
	return Object.fromEntries(SPACING_SIDES.map(({ property, side, arrow }) => [side, `${arrow} ${spacingText(style[property])}`])) as Record<SpacingSide, string>;
}
