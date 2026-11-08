// Manuels et notices (/assistance) : GET /api/v2/shop/yack/assistance/manuals (public) et interrupteur
// YACK_ASSISTANCE (src/env.ts : « 0 » coupe la page, l'API répond 404 de son côté). Lecture : #lib/assistance/assistance.ts.
import { YACK_ASSISTANCE } from '$app/env/private';
import { isEmptyInput, toManualSearch, validateFields } from '#lib/assistance/assistance.js';
import type { AssistanceInput, ManualsPage } from '#lib/assistance/types.js';
import { ApiError, type ShopClient } from './sylius.js';

/** Le module est-il actif (YACK_ASSISTANCE différent de « 0 ») ? */
export const isAssistanceEnabled = (): boolean => YACK_ASSISTANCE === true;

/**
 * Recherche de la page : `q` (saisie libre), sinon `reference` et `serial`. La marque n'est pas un critère de
 * l'API : elle reste dans l'URL. Saisie vide : rien n'est demandé. Champs refusés (navigateur, puis 422 de
 * l'API) : le message, sans résultat. Le 404 de l'API (module coupé côté Sylius) remonte.
 */
export async function findManuals(api: ShopClient, input: AssistanceInput): Promise<ManualsPage> {
	if (isEmptyInput(input)) return { input, search: null, error: null };

	const issue = Object.values(validateFields(input))[0];
	if (issue) return { input, search: null, error: issue };

	try {
		const raw = await api.get<unknown>(
			'/yack/assistance/manuals',
			input.q !== '' ? { q: input.q } : { reference: input.ref, serial: input.sn }
		);
		return { input, search: toManualSearch(raw), error: null };
	} catch (error) {
		if (error instanceof ApiError && error.status === 422) return { input, search: null, error: error.message };
		throw error;
	}
}
