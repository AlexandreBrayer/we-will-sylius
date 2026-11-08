// Manuels et notices (/assistance) : GET /api/v2/shop/yack/assistance/manuals et POST …/plate-readings (publics),
// POST …/questions (comptes connectés : l'API répond 401 à un visiteur, aucun appel n'est fait pour lui),
// interrupteur YACK_ASSISTANCE (src/env.ts : « 0 » coupe la page, l'API répond 404 de son côté). Lecture des
// réponses : #lib/assistance/assistance.ts.
import { YACK_ASSISTANCE } from '$app/env/private';
import { isEmptyInput, plateCheck, toManualAnswer, toManualSearch, toPlateReading, unavailablePlate, validateFields } from '#lib/assistance/assistance.js';
import type { AssistanceInput, ManualAnswer, ManualsPage, PlateCheck } from '#lib/assistance/types.js';
import { ApiError, type ShopClient } from './sylius.js';
import { uploadFormData } from './upload.js';

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

/**
 * Photo de la plaque → écran « C'est bien ça ? » (multipart, champ `photo`). L'API lit sans rien chercher et ne garde
 * pas la photo. 503 (lecture indisponible) : l'écran de saisie, champs vides ; les autres erreurs remontent (422
 * affiché sur le formulaire photo, 404 du module coupé).
 */
export async function readPlatePhoto(api: ShopClient, photo: File): Promise<PlateCheck> {
	try {
		const raw = await api.postForm<unknown>('/yack/assistance/plate-readings', await uploadFormData({}, { photo }));
		return plateCheck(toPlateReading(raw));
	} catch (error) {
		if (error instanceof ApiError && error.status === 503) return unavailablePlate();
		throw error;
	}
}

/**
 * Question aux manuels indexés du modèle `reference` (documents exact et série dont le texte est indexé). L'API
 * choisit les documents, lit les passages et rend les sources à la page ; ses refus (422, 503) remontent.
 */
export async function askManualQuestion(api: ShopClient, question: string, reference: string): Promise<ManualAnswer> {
	const raw = await api.post<unknown>('/yack/assistance/questions', { question: question.trim(), reference: reference.trim() });
	return toManualAnswer(raw);
}
