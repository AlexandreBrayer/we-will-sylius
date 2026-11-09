import { getContext, setContext } from 'svelte';
import { VISITOR, type SortKey, type Viewer } from '#lib/types.js';
import type { Catalog, ProductContext, TaxonContext, TaxonListing } from './types.js';

/** Builder canvas data sources (builder remote functions), instead of the server-side data of public pages. */
export interface EditorLoaders {
	/** Products / taxons of the free blocks "produits" / "categories". */
	catalog(query: { products: string[]; taxons: string[] }): Promise<Catalog>;
	/** Products of the sample category for the initial sort / page size of "categorie-produits". */
	listing(query: { taxonCode: string; sort: SortKey; perPage: number }): Promise<TaxonListing>;
}

export interface YackContext {
	/** Who is browsing: product prices / notices of the "produits" block. */
	viewer: Viewer;
	/** Products / taxons resolved server-side (public pages); null = components use `loaders.catalog`. */
	catalog: Catalog | null;
	/** Rendering inside the builder canvas. */
	editor: boolean;
	/** Product of a product template (fiche produit, or the sample product of the builder). */
	product?: ProductContext | null;
	/** Category of a category template (page catégorie, or the sample category of the builder). */
	taxon?: TaxonContext | null;
	/** Builder canvas only. */
	loaders?: EditorLoaders | null;
}

/** Text prop of a block shown by an element: the prop, or a column of an element of a list prop. */
export interface TextTarget {
	prop: string;
	/** List prop (FAQ, documents): how the shop reads it, element shown at `item`, column `column`. */
	list?: { kind: 'faq' | 'links'; item: number; column: number };
}

/**
 * Builder canvas only (D8): in-place editing of the texts of the block, given by the canvas to each block
 * it mounts (#lib/editor/inline/textEditing.ts). Read through textEditor() (editable.ts).
 */
export interface BlockTextEditing {
	/** Makes `el`, which shows `target`, editable in place; returns what undoes it. */
	attach(el: HTMLElement, target: TextTarget): () => void;
}

const KEY = Symbol('yack-cms');
const TEXT_EDITING_KEY = Symbol('yack-cms-text-editing');

/** Context of a block mounted by hand (builder canvas), with its in-place text editing. */
export function yackContextMap(context: YackContext, textEditing?: BlockTextEditing): Map<symbol, YackContext | BlockTextEditing> {
	const map = new Map<symbol, YackContext | BlockTextEditing>([[KEY, context]]);
	if (textEditing) map.set(TEXT_EDITING_KEY, textEditing);
	return map;
}

/** In-place text editing of the block; null outside the builder canvas (shop: plain text). */
export function getTextEditing(): BlockTextEditing | null {
	return getContext<BlockTextEditing | undefined>(TEXT_EDITING_KEY) ?? null;
}

export function setYackContext(context: YackContext): void {
	setContext(KEY, context);
}

export function getYackContext(): YackContext {
	return getContext<YackContext>(KEY) ?? { viewer: VISITOR, catalog: null, editor: false };
}
