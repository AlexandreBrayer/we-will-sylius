import type { Catalog } from './types.js';
import type { YackContext } from './context.js';

/**
 * Products / taxons of a block: from the server-side catalog (public pages, SSR) or, in the
 * builder canvas, loaded through `ctx.loaders.catalog` (builder remote function).
 */
export function useCatalog(ctx: YackContext, query: () => { products?: string[]; taxons?: string[] }) {
	let fetched = $state<Catalog | null>(null);

	$effect(() => {
		if (ctx.catalog || !ctx.loaders) return;
		const { products = [], taxons = [] } = query();
		let cancelled = false;
		ctx.loaders
			.catalog({ products, taxons })
			.then((catalog) => {
				if (!cancelled) fetched = catalog;
			})
			.catch(() => undefined);
		return () => {
			cancelled = true;
		};
	});

	return {
		get current(): Catalog | null {
			return ctx.catalog ?? fetched;
		}
	};
}
