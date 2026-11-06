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

const KEY = Symbol('yack-cms');

export function yackContextMap(context: YackContext): Map<symbol, YackContext> {
	return new Map([[KEY, context]]);
}

export function setYackContext(context: YackContext): void {
	setContext(KEY, context);
}

export function getYackContext(): YackContext {
	return getContext<YackContext>(KEY) ?? { viewer: VISITOR, catalog: null, editor: false };
}
