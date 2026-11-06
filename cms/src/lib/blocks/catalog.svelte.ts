import type { Catalog } from './types.js';
import type { YackContext } from './context.js';

/**
 * Products / taxons of a block: from the server-side catalog (public pages, SSR) or, in the
 * builder canvas, fetched from /api/catalog (proxy to the Sylius shop API).
 */
export function useCatalog(ctx: YackContext, query: () => { products?: string[]; taxons?: string[] }) {
	let fetched = $state<Catalog | null>(null);

	$effect(() => {
		if (ctx.catalog) return;
		const { products = [], taxons = [] } = query();
		const params = new URLSearchParams({ products: products.join(','), taxons: taxons.join(',') });
		let cancelled = false;
		fetch(`/api/catalog?${params}`)
			.then((response) => (response.ok ? response.json() : null))
			.then((catalog: Catalog | null) => {
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
