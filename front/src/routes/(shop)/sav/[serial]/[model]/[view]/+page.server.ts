import { error, fail, redirect, type RequestEvent } from '@sveltejs/kit';
import { addToCart } from '#lib/server/cart.js';
import { findExplodedView, findSerial, savBreadcrumb } from '#lib/server/sav.js';
import { loginUrl } from '#lib/server/session.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

type ViewEvent = RequestEvent<{ serial: string; model: string; view: string }>;

/** Exploded view of a model of the device searched (404 when the URL segments do not belong together). */
async function explodedView(event: ViewEvent) {
	const { serial, model, view } = event.params;
	const id = Number(view);
	if (!Number.isInteger(id) || id < 1) error(404, 'Vue éclatée introuvable');

	const api = sylius(event);
	const [device, explodedView] = await Promise.all([findSerial(api, serial), findExplodedView(api, id)]);
	if (
		!device ||
		!explodedView ||
		explodedView.model.serviceCode !== model ||
		!device.models.some((m) => m.serviceCode === model)
	) {
		error(404, 'Vue éclatée introuvable');
	}
	return explodedView;
}

export const load: PageServerLoad = async (event) => {
	const view = await explodedView(event);
	return { serial: event.params.serial, view, breadcrumb: savBreadcrumb(event.params.serial, view.model, view.name) };
};

export const actions: Actions = {
	/** "Ajouter" a part of the view (ECOM) into the same cart as the products (port of the Twig SAV cart adder). */
	addToCart: async (event) => {
		const { viewer } = event.locals;
		if (!viewer.authenticated) redirect(303, loginUrl(new URL(event.url.pathname, event.url)));
		if (!viewer.canOrder) return fail(403, { marker: null, error: 'La commande en ligne est réservée aux comptes ECOM.' });

		const form = await event.request.formData();
		const marker = Number(form.get('marker'));
		const quantity = Number(form.get('quantity'));
		const item = (await explodedView(event)).items.find((candidate) => candidate.marker === marker);
		if (!item) return fail(400, { marker: null, error: 'Pièce introuvable dans cette vue éclatée.' });
		if (!item.part.enabled || !item.part.variantCode) {
			return fail(400, { marker, error: 'Cette pièce n\'est plus commercialisée.' });
		}
		if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99) {
			return fail(400, { marker, error: 'Saisissez une quantité entière entre 1 et 99.' });
		}

		try {
			await addToCart(event, item.part.variantCode, quantity);
			return { marker, added: quantity, part: item.part.name };
		} catch (e) {
			if (e instanceof ApiError && e.status < 500) return fail(e.status, { marker, error: e.message });
			throw e;
		}
	}
};
