import { ApiError, sylius } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

/** Link of the activation e-mail: POST /shop/yack/activations/{token} (204, or 404 when invalid / used). */
export const load: PageServerLoad = async (event) => {
	try {
		await sylius(event).post(`/yack/activations/${encodeURIComponent(event.params.token)}`);
		return { activated: true, error: null };
	} catch (error) {
		if (error instanceof ApiError && error.status < 500) {
			return { activated: false, error: error.status === 404 ? "Lien d'activation invalide ou déjà utilisé." : error.message };
		}
		throw error;
	}
};
