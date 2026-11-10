// Palette of the builder (E6): the rubriques in their order, « Mise en page » first and open (the basic
// blocks, then the layout containers), the search over the labels of every rubrique (case and accents
// ignored), and the two gestures of a block of the palette (click: added at the end of the page; drag:
// dropped where the canvas shows), through the Blocks API of GrapesJS, as « Mes sections » does
// (panels/sectionCanvas.ts). No runtime import: tested as it is (`npm test`, test/palette.test.ts).
import type { Block, Editor } from 'grapesjs';

/** Rubrique of the basic blocks and of the layout containers: first, open when the builder opens. */
export const LAYOUT_CATEGORY = 'Mise en page';

/** Rubriques of the palette, in order; « Mes sections » (panels/SectionPalette.svelte) comes after them. */
export const CATEGORY_ORDER: readonly string[] = [LAYOUT_CATEGORY, 'Blocs produit', 'Blocs catégorie', 'Blocs Yack'];

/**
 * Blocks of « Mise en page », in palette order: the basic blocks first (the ones declared in the registry;
 * a type absent from it is skipped), then the layout containers.
 */
export const LAYOUT_ORDER: readonly string[] = ['titre', 'texte', 'bouton', 'espace', 'texte-image', 'image', 'section', 'colonnes'];

/** What the palette needs of a block of the registry. */
export interface PaletteEntry {
	type: string;
	label: string;
	category: string;
}

/** Rubrique as the palette shows it: its blocks (those matching the search) and whether it is open. */
export interface PaletteGroup<T extends PaletteEntry> {
	category: string;
	items: T[];
	open: boolean;
}

/** Text compared by the search: lower case, no accent, typographic apostrophe as a plain one, single spaces. */
export function normalizeSearch(text: string): string {
	return text
		.normalize('NFD')
		.replace(/\p{M}/gu, '')
		.replace(/[’‘`]/g, "'")
		.toLowerCase()
		.replace(/\s+/g, ' ')
		.trim();
}

/** A label matches when it holds every word of the search (in any order); an empty search matches everything. */
export function matchesSearch(label: string, query: string): boolean {
	const words = normalizeSearch(query).split(' ').filter((word) => word !== '');
	const text = normalizeSearch(label);
	return words.every((word) => text.includes(word));
}

const rank = (list: readonly string[], value: string): number => {
	const index = list.indexOf(value);
	return index === -1 ? list.length : index;
};

/**
 * Blocks in palette order: by rubrique (CATEGORY_ORDER, unknown rubriques last), « Mise en page » in
 * LAYOUT_ORDER; otherwise the order of the registry is kept.
 */
export function orderPalette<T extends PaletteEntry>(entries: readonly T[]): T[] {
	return entries
		.map((entry, index) => ({ entry, index }))
		.sort(
			(a, b) =>
				rank(CATEGORY_ORDER, a.entry.category) - rank(CATEGORY_ORDER, b.entry.category) ||
				(a.entry.category === LAYOUT_CATEGORY ? rank(LAYOUT_ORDER, a.entry.type) - rank(LAYOUT_ORDER, b.entry.type) : 0) ||
				a.index - b.index
		)
		.map(({ entry }) => entry);
}

/** Rubriques open when the builder opens: « Mise en page » only. */
export function initiallyOpen(): string[] {
	return [LAYOUT_CATEGORY];
}

/**
 * Rubriques of the palette, blocks given in palette order. No search: every rubrique, open when the
 * administrator left it open (`opened`). A search: only the rubriques holding a match, with their matches,
 * open unless closed during this search (`closedInSearch`).
 */
export function paletteGroups<T extends PaletteEntry>(
	entries: readonly T[],
	query: string,
	opened: readonly string[],
	closedInSearch: readonly string[] = []
): PaletteGroup<T>[] {
	const searching = normalizeSearch(query) !== '';
	const groups: PaletteGroup<T>[] = [];
	for (const entry of entries) {
		if (searching && !matchesSearch(entry.label, query)) continue;
		let group = groups.find((candidate) => candidate.category === entry.category);
		if (!group) {
			group = { category: entry.category, items: [], open: searching ? !closedInSearch.includes(entry.category) : opened.includes(entry.category) };
			groups.push(group);
		}
		group.items.push(entry);
	}
	return groups;
}

/** `list` with `value` added, or taken out when it is there (open rubriques, rubriques closed in a search). */
export function toggled(list: readonly string[], value: string): string[] {
	return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
}

/** Identifier of a rubrique in the page (`aria-controls`): ASCII, lower case, dashes. */
export function categoryId(category: string): string {
	return `palette-${normalizeSearch(category).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`;
}

/** What the gestures use of the editor (a stub in the tests). */
export type PaletteEditor = Pick<Editor, 'Blocks'>;

/**
 * Click (or Entrée, Espace) on a block of the palette: the GrapesJS block's own behaviour, set by
 * grapesSetup.ts (added at the end of the page, in its zone, selected and shown).
 */
export function insertPaletteBlock(editor: PaletteEditor, type: string): boolean {
	const block = editor.Blocks.get(type);
	const onClick: unknown = block?.get('onClick');
	if (!block || typeof onClick !== 'function') return false;
	(onClick as (block: Block, editor: PaletteEditor, options: Record<string, unknown>) => void)(block, editor, {});
	return true;
}

/**
 * Starts dragging a block of the palette (native `dragstart` of its button): the canvas shows where it
 * goes and drops it, as the palette of GrapesJS did. Returns the function the `dragend` of the button
 * calls, or null when the block is not in the editor (the drag is cancelled).
 */
export function startPaletteDrag(editor: PaletteEditor, type: string, event: DragEvent): ((event: DragEvent) => void) | null {
	const block = editor.Blocks.get(type);
	if (!block) {
		event.preventDefault();
		return null;
	}
	// Firefox starts a native drag only with data; the canvas reads the content of the block, not this text.
	event.dataTransfer?.setData('text/plain', '');
	if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
	editor.Blocks.startDrag(block, event);
	return (end: DragEvent) => editor.Blocks.endDrag(end.dataTransfer?.dropEffect === 'none');
}
