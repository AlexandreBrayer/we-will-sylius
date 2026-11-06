import { parseCodes } from '#lib/blocks/parse.js';
import { blockCatalog } from '#lib/server/catalog.js';
import { shopClient } from '#lib/server/sylius.js';
import { VISITOR } from '#lib/types.js';
import type { RequestHandler } from './$types';

/** Products / taxons for the builder canvas (shop API as a visitor: no prices). */
export const GET: RequestHandler = async ({ url }) =>
	Response.json(
		await blockCatalog(
			shopClient(null),
			VISITOR,
			parseCodes(url.searchParams.get('products') ?? '').slice(0, 24),
			parseCodes(url.searchParams.get('taxons') ?? '').slice(0, 24)
		)
	);
