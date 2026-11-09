// After-sales (SAV) API: GET /api/v2/shop/yack/sav/* (public; part prices only for ECOM accounts),
// POST yack/sav/nameplate-readings (photo of the rating plate or label).
import type { Breadcrumb } from '#lib/types.js';
import { nameplateOutcome, unavailableNotice, type NameplateOutcome, type NameplateReading } from '#lib/sav/nameplate.js';
import { savUrl, type ExplodedView, type SavModel, type SavSerial } from '#lib/sav/types.js';
import { ApiError, type ShopClient } from './sylius.js';
import { uploadFormData } from './upload.js';

/** Serial number (case, spaces, `-_.` tolerated by the API) or ServiceCode; null when unknown. */
export const findSerial = (api: ShopClient, query: string) =>
	api.find<SavSerial>(`/yack/sav/serials/${encodeURIComponent(query)}`);

export const findModel = (api: ShopClient, serviceCode: string) =>
	api.find<SavModel>(`/yack/sav/models/${encodeURIComponent(serviceCode)}`);

export const findExplodedView = (api: ShopClient, id: number) =>
	api.find<ExplodedView>(`/yack/sav/exploded-views/${id}`);

/** Accueil / SAV / {serial} / {model} / {view}, separated by the bar of the breadcrumb (port of sav/_breadcrumb.html.twig). */
export function savBreadcrumb(key: string, model?: { serviceCode: string; name: string }, view?: string): Breadcrumb[] {
	return [
		{ label: 'Accueil', url: '/' },
		{ label: 'SAV', url: '/sav' },
		{ label: key, url: model ? savUrl(key) : null },
		...(model ? [{ label: model.name, url: view ? savUrl(key, model.serviceCode) : null }] : []),
		...(view ? [{ label: view, url: null }] : [])
	];
}

/**
 * Photo of the rating plate or label → serial number looked up in the installed base, else model reference
 * looked up in the catalog, by the API (multipart, field `photo`; the API checks the type on the content and
 * keeps nothing). A 503 (reading service unavailable) becomes the notice « Lecture indisponible », and so does any
 * other server error of the API (a 500, such as an incomplete AI configuration, is logged with its status): the
 * visitor gets the next step, never « vérifiez la connexion ». Other API errors go up (422 shown on the form).
 */
export async function identifyFromPhoto(api: ShopClient, photo: File): Promise<NameplateOutcome> {
	try {
		const reading = await api.postForm<NameplateReading>('/yack/sav/nameplate-readings', await uploadFormData({}, { photo }));
		return nameplateOutcome(reading);
	} catch (error) {
		if (!(error instanceof ApiError) || error.status < 500) throw error;
		if (error.status !== 503) console.warn(`Lecture de plaque (SAV) : l'API répond ${error.status}`);
		return { found: false, ...unavailableNotice() };
	}
}
