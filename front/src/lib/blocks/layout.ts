// Page composition shared by the shop (TemplateView, BlockRenderer) and the builder canvas (zones.ts,
// layouts.ts of #lib/editor), so that both produce the same markup:
//  - product zones of a template: consecutive product blocks with a `column` (registry) form one
//    two-column zone of the Sylius product page — main column (gallery, description…) and sticky
//    side box (infos, purchase, SAV link); every other block (layout containers included) spans the
//    page. The stored template stays a flat block list at its root: the zones are derived from it,
//    and written back in their DOM order;
//  - layout containers (section, colonnes: #lib/blocks/containers.ts): classes of the container, of the
//    row of columns and of a column (SCSS: _cms-layout.scss).
import { COLUMN_ALIGNMENTS, COLUMN_GAPS, columnRatio, type ContainerType } from './containers.js';
import { blockDefinition } from './registry.js';
import { layoutStyleToCss } from './style.js';
import type { BlockProps, PageBlock } from './types.js';

export type ProductColumn = 'main' | 'side';

/** A block of a section: a saved block (PageBlock) or a block of the builder (revision, section). */
type LaidOut = { type: string };

export type Section<B extends LaidOut = PageBlock> = { kind: 'block'; block: B } | { kind: 'product'; side: B[]; main: B[] };

/** Column of a product block in the product zone, null for a full-width block. */
export function productColumn(type: unknown): ProductColumn | null {
	return typeof type === 'string' ? (blockDefinition(type)?.column ?? null) : null;
}

/** Sections of the page: full-width blocks and product zones, in the order of the blocks. */
export function layoutRuns<B extends LaidOut>(blocks: readonly B[]): Section<B>[] {
	const sections: Section<B>[] = [];
	for (const block of blocks) {
		const column = productColumn(block.type);
		if (column === null) {
			sections.push({ kind: 'block', block });
			continue;
		}
		let last = sections.at(-1);
		if (last?.kind !== 'product') {
			last = { kind: 'product', side: [], main: [] };
			sections.push(last);
		}
		last[column].push(block);
	}
	return sections;
}

/** Flat block list of sections (zone: side box then main column, their DOM order): `layoutRuns` regroups it identically. */
export function flattenRuns(sections: Section[]): PageBlock[] {
	return sections.flatMap((section) => (section.kind === 'block' ? [section.block] : [...section.side, ...section.main]));
}

/** Markup of a product zone (Sylius product page), the same on the shop and in the builder canvas. */
export const PRODUCT_ZONE = {
	zone: 'container mb-5',
	row: 'row g-3 g-lg-5',
	/** Side column (first in the DOM: above the gallery on mobile, on the right from lg). */
	side: 'col-12 col-lg-5 order-lg-1',
	/** Sticky box of the side column, holding its blocks (_catalog.scss). */
	sideBox: 'yack-product__side',
	main: 'col-12 col-lg-7'
} as const;

/** Classes, custom properties (`style`) and anchor of the element of a layout container. */
export interface LayoutMarkup {
	class: string;
	style: string | undefined;
	id: string | undefined;
}

/**
 * Element of a section / colonnes: `yack-cms-layout yack-cms-layout--<type>`, one modifier per style property in
 * effect (`--fond`, `--width-pleine`, `--hide-mobile`…) and `--editor` in the builder canvas (hidden containers
 * stay visible there, empty ones show a drop area).
 */
export function layoutMarkup(type: ContainerType, style: unknown, editor: boolean): LayoutMarkup {
	const css = layoutStyleToCss(style);
	const modifiers = [type, ...css.modifiers, ...(editor ? ['editor'] : [])];
	return {
		class: ['yack-cms-layout', ...modifiers.map((modifier) => `yack-cms-layout--${modifier}`)].join(' '),
		style: css.style || undefined,
		id: css.id ?? undefined
	};
}

/** Markup of a colonnes inside its container element: `container` > row of columns > columns. */
export const COLUMNS_LAYOUT = {
	inner: 'container',
	column: 'yack-cms-cols__col'
} as const;

/** Row of the columns: ratio, vertical alignment and gap of the props (unknown values: the defaults of BlockType). */
export function columnsRowClass(props: BlockProps): string {
	const alignment = COLUMN_ALIGNMENTS.find((value) => value === props.alignement) ?? COLUMN_ALIGNMENTS[0];
	const gap = COLUMN_GAPS.find((value) => value === props.espacement) ?? COLUMN_GAPS[0];
	return `yack-cms-cols yack-cms-cols--${columnRatio(props.ratio)} yack-cms-cols--align-${alignment} yack-cms-cols--gap-${gap}`;
}
