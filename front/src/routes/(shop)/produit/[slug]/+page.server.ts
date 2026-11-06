import { error, fail, redirect } from '@sveltejs/kit';
import { addToCart } from '#lib/server/cart.js';
import { breadcrumb, catalogTree, latestProducts, productBySlug, taxonPath } from '#lib/server/catalog.js';
import { loginUrl } from '#lib/server/session.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const { viewer } = event.locals;
	const api = sylius(event);
	const [product, roots, latest] = await Promise.all([
		productBySlug(api, viewer, event.params.slug),
		catalogTree(),
		latestProducts(api, viewer)
	]);
	if (!product) error(404, 'Produit introuvable');

	const trail = product.mainTaxonCode ? taxonPath(roots, (node) => node.code === product.mainTaxonCode) : [];

	return { product, breadcrumb: breadcrumb(trail, product.name), latest };
};

export const actions: Actions = {
	/** "Ajouter au panier" (ECOM): POST /api/v2/shop/orders/{cart}/items. */
	addToCart: async (event) => {
		const { viewer } = event.locals;
		if (!viewer.authenticated) redirect(303, loginUrl(new URL(event.url.pathname, event.url)));
		if (!viewer.canOrder) return fail(403, { error: 'La commande en ligne est réservée aux comptes ECOM.' });

		const form = await event.request.formData();
		const variant = String(form.get('variant') ?? '');
		const quantity = Number(form.get('quantity'));
		if (!variant) return fail(400, { error: 'Produit indisponible à la commande.' });
		if (!Number.isInteger(quantity) || quantity < 1 || quantity > 9999) {
			return fail(400, { error: 'Saisissez une quantité entière supérieure ou égale à 1.' });
		}

		try {
			const cart = await addToCart(event, variant, quantity);
			return { added: quantity, cart };
		} catch (e) {
			if (e instanceof ApiError && e.status < 500) return fail(e.status, { error: e.message });
			throw e;
		}
	}
};
