// After-sales (/sav/**): serial number → models → exploded views → parts (#lib/server/sav.ts).
// Part prices only for ECOM accounts (applied by the API). Adding a part to the cart: `addToCart` (cart.remote.ts).
// « La pièce par une photo »: form `identifyFromPhoto` of /sav.
import { error, invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { PHOTO_MESSAGES, photoIssue, type NameplateNotice } from '#lib/sav/nameplate.js';
import { findExplodedView, findModel, findSerial, identifyFromPhoto as identify, savBreadcrumb } from '#lib/server/sav.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import { savKey, savUrl, type ExplodedView, type SavModel, type SavSerial } from '#lib/sav/types.js';
import type { Breadcrumb } from '#lib/types.js';

/** SAV home (/sav): the GET search form `?serial=` (page URL) redirects to /sav/{serial}. */
export const getSavHome = query(v.optional(v.string()), (serial): { breadcrumb: Breadcrumb[] } => {
	const search = serial?.trim();
	if (search) redirect(303, savUrl(search));
	return { breadcrumb: [{ label: 'Accueil', url: '/' }, { label: 'SAV', url: null }] };
});

/**
 * Warranty and models of a serial number (or ServiceCode); `result` null when unknown.
 * One URL per device: `yk19-kaz35s-004567` redirects to /sav/YK19KAZ35S004567.
 */
export const getSavSerial = query(
	v.string(),
	async (serial): Promise<{ query: string; result: SavSerial | null; breadcrumb: Breadcrumb[] }> => {
		const result = await findSerial(sylius(getRequestEvent()), serial);
		if (!result) return { query: serial, result: null, breadcrumb: savBreadcrumb(serial) };

		const key = savKey(result);
		if (key !== serial) redirect(308, savUrl(key));
		return { query: key, result, breadcrumb: savBreadcrumb(key) };
	}
);

/** Exploded views of a model (ServiceCode) of the device; 404 when the model does not belong to it. */
export const getSavModel = query(
	v.object({ serial: v.string(), model: v.string() }),
	async ({ serial, model: serviceCode }): Promise<{ model: SavModel; breadcrumb: Breadcrumb[] }> => {
		const api = sylius(getRequestEvent());
		const [device, model] = await Promise.all([findSerial(api, serial), findModel(api, serviceCode)]);
		if (!device || !model || !device.models.some((m) => m.serviceCode === model.serviceCode)) {
			error(404, 'Modèle introuvable pour cet appareil');
		}
		return { model, breadcrumb: savBreadcrumb(serial, model) };
	}
);

/** Exploded view (hotspots + parts) of a model of the device; 404 when the URL segments do not belong together. */
export const getExplodedView = query(
	v.object({ serial: v.string(), model: v.string(), view: v.string() }),
	async ({ serial, model, view }): Promise<{ view: ExplodedView; breadcrumb: Breadcrumb[] }> => {
		const id = Number(view);
		if (!Number.isInteger(id) || id < 1) error(404, 'Vue éclatée introuvable');

		const api = sylius(getRequestEvent());
		const [device, explodedView] = await Promise.all([findSerial(api, serial), findExplodedView(api, id)]);
		if (
			!device ||
			!explodedView ||
			explodedView.model.serviceCode !== model ||
			!device.models.some((m) => m.serviceCode === model)
		) {
			error(404, 'Vue éclatée introuvable');
		}
		return { view: explodedView, breadcrumb: savBreadcrumb(serial, explodedView.model, explodedView.name) };
	}
);

/** Photo field: the rules of the browser (`photoIssue`: JPEG, PNG or WebP, HEIC named, 20 Mo), checked again here. */
const photoField = v.pipe(
	v.file(PHOTO_MESSAGES.missing),
	v.check(
		(file) => photoIssue(file) === null,
		(issue) => photoIssue(issue.input) ?? PHOTO_MESSAGES.format
	)
);

/**
 * Photo of the rating plate or label (multipart, `enctype="multipart/form-data"`). Found: redirect to
 * /sav/<serial>, the same page as a manual search. Otherwise the notice (catalog product, unreadable,
 * unknown + contact, unavailable) is the result. Checks of the file on the field; API refusals (422) on the form.
 */
export const identifyFromPhoto = form(
	// Message of the object: the one of a missing key, i.e. a submit without a file (without JavaScript, the form is `novalidate`).
	v.object({ photo: photoField }, PHOTO_MESSAGES.missing),
	async ({ photo }): Promise<NameplateNotice> => {
		let outcome;
		try {
			outcome = await identify(sylius(getRequestEvent()), photo);
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			invalid(error.message);
		}
		if (outcome.found) redirect(303, savUrl(outcome.serialNumber));
		const { found: _found, ...notice } = outcome;
		return notice;
	}
);
