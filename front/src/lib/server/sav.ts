// After-sales (SAV) API: GET /api/v2/shop/yack/sav/* (public; part prices only for ECOM accounts).
import type { Breadcrumb } from '#lib/types.js';
import { savUrl, type ExplodedView, type SavModel, type SavSerial } from '#lib/sav/types.js';
import type { ShopClient } from './sylius.js';

/** Serial number (case, spaces, `-_.` tolerated by the API) or ServiceCode; null when unknown. */
export const findSerial = (api: ShopClient, query: string) =>
	api.find<SavSerial>(`/yack/sav/serials/${encodeURIComponent(query)}`);

export const findModel = (api: ShopClient, serviceCode: string) =>
	api.find<SavModel>(`/yack/sav/models/${encodeURIComponent(serviceCode)}`);

export const findExplodedView = (api: ShopClient, id: number) =>
	api.find<ExplodedView>(`/yack/sav/exploded-views/${id}`);

/** Accueil › SAV — {serial} › {model} › {view} (port of sav/_breadcrumb.html.twig). */
export function savBreadcrumb(key: string, model?: { serviceCode: string; name: string }, view?: string): Breadcrumb[] {
	return [
		{ label: 'Accueil', url: '/' },
		{ label: `SAV — ${key}`, url: model ? savUrl(key) : null },
		...(model ? [{ label: model.name, url: view ? savUrl(key, model.serviceCode) : null }] : []),
		...(view ? [{ label: view, url: null }] : [])
	];
}
