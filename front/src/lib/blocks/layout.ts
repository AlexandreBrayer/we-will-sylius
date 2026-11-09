// Page composition of a template, shared by the shop (TemplateView) and the builder canvas (product
// zones of #lib/editor/zones.ts): consecutive product blocks with a `column` (registry) form one
// two-column zone of the Sylius product page — main column (gallery, description…) and sticky
// side box (infos, purchase, SAV link); every other block spans the page. The stored template
// stays a flat block list: the zones are derived from it, and written back in their DOM order.
import { blockDefinition } from './registry.js';
import type { PageBlock } from './types.js';

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
