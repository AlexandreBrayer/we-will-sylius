import { json } from '@sveltejs/kit';
import { parseCodes } from '$lib/blocks/parse.js';
import { catalog } from '$lib/server/sylius.js';
import type { RequestHandler } from './$types';

/** Products / taxons for the builder canvas (proxy to the public Sylius shop API). */
export const GET: RequestHandler = async ({ url }) =>
	json(
		await catalog(
			parseCodes(url.searchParams.get('products') ?? '').slice(0, 24),
			parseCodes(url.searchParams.get('taxons') ?? '').slice(0, 24)
		)
	);
