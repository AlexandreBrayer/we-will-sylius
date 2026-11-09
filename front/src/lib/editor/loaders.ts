// Canvas data of the builder (YackContext.loaders): builder remote queries, read as the profile of
// the canvas ("Voir comme" of the template editor; visitor for CMS pages).
import type { EditorLoaders } from '#lib/blocks/context.js';
import { getBlockCatalog, getPreviewListing } from '#lib/remote/builder.remote.js';
import type { Profile } from '#lib/types.js';

type ListingQuery = Parameters<typeof getPreviewListing>[0];

/** `profile` is read when a block loads: a block re-loads when it changes (reactive getter). */
export function editorLoaders(profile: () => Profile): EditorLoaders {
	return {
		catalog: (codes) => getBlockCatalog({ ...codes, as: profile() }),
		// The block offers the grid sorts only (no price sort): see the "categorie-produits" traits.
		listing: (query) => getPreviewListing({ ...(query as Omit<ListingQuery, 'as'>), as: profile() })
	};
}
