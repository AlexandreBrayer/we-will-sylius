// Canvas data of the builder (YackContext.loaders): builder remote queries, read as a visitor.
import type { EditorLoaders } from '#lib/blocks/context.js';
import { getBlockCatalog, getPreviewListing } from '#lib/remote/builder.remote.js';

type ListingQuery = Parameters<typeof getPreviewListing>[0];

export const editorLoaders: EditorLoaders = {
	catalog: (codes) => getBlockCatalog(codes),
	// The block offers the grid sorts only (no price sort): see the "categorie-produits" traits.
	listing: (query) => getPreviewListing(query as ListingQuery)
};
