// Manuels et notices (/assistance) : recherche des documents d'un appareil, lecture de la photo de sa plaque et
// question aux manuels indexés du modèle. Appels : #lib/server/assistance.ts.
import { error, invalid } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { QUESTION_MAX, QUESTION_MESSAGES, QUESTION_MIN, REFERENCE_MAX } from '#lib/assistance/assistance.js';
import type { ManualAnswer, ManualsPage, PlateCheck } from '#lib/assistance/types.js';
import { PHOTO_MESSAGES, photoIssue } from '#lib/sav/nameplate.js';
import { askManualQuestion, findManuals, isAssistanceEnabled, readPlatePhoto } from '#lib/server/assistance.js';
import { ApiError, sylius } from '#lib/server/sylius.js';

/** Le module est-il actif ? (YACK_ASSISTANCE : liens et entrées de menu affichés seulement s'il l'est.) */
export const assistanceEnabled = query((): boolean => isAssistanceEnabled());

const field = v.optional(v.string(), '');

/**
 * Résultats de la page pour la saisie de son URL (`q`, ou `ref`, `sn`, `marque`). Module coupé : 404, la page
 * n'existe pas. Saisie vide : `search` null, la page montre la barre seule.
 */
export const getManuals = query(
	v.object({ q: field, ref: field, sn: field, marque: field }),
	async (input): Promise<ManualsPage> => {
		if (!isAssistanceEnabled()) error(404, 'Page introuvable');
		try {
			return await findManuals(sylius(getRequestEvent()), input);
		} catch (cause) {
			if (cause instanceof ApiError && cause.status === 404) error(404, 'Page introuvable');
			throw cause;
		}
	}
);

/** Champ photo : les règles du navigateur (`photoIssue` : JPEG, PNG ou WebP, HEIC nommé, 20 Mo), contrôlées ici aussi. */
const photoField = v.pipe(
	v.file(PHOTO_MESSAGES.missing),
	v.check(
		(file) => photoIssue(file) === null,
		(issue) => photoIssue(issue.input) ?? PHOTO_MESSAGES.format
	)
);

/**
 * Photo de la plaque signalétique ou de la fiche technique (multipart, `enctype="multipart/form-data"`) : rend l'écran
 * « C'est bien ça ? », sans aucune recherche (lue, illisible ou indisponible : jamais d'impasse). Contrôles du fichier
 * sur le champ ; refus de l'API (422) sur le formulaire. Module coupé : 404.
 */
export const readPlate = form(
	// Message de l'objet : celui d'une clé absente, soit l'envoi sans fichier (sans JavaScript, le formulaire est `novalidate`).
	v.object({ photo: photoField }, PHOTO_MESSAGES.missing),
	async ({ photo }): Promise<PlateCheck> => {
		if (!isAssistanceEnabled()) error(404, 'Page introuvable');
		try {
			return await readPlatePhoto(sylius(getRequestEvent()), photo);
		} catch (cause) {
			if (cause instanceof ApiError && cause.status === 404) error(404, 'Page introuvable');
			if (!(cause instanceof ApiError) || cause.status >= 500) throw cause;
			invalid(cause.message);
		}
	}
);

/**
 * « Une question sur ce modèle ? » : question aux manuels indexés de la référence (champ caché), comptes connectés
 * seulement. Marche sans JavaScript (POST natif, page rendue par le serveur avec la réponse). Résultat : la réponse,
 * ses sources à la page et la question posée ; refus de l'API (422) et indisponibilité (503) sur le formulaire.
 * Module coupé : 404.
 */
export const askManuals = form(
	v.object({
		question: v.pipe(v.optional(v.string(), ''), v.trim(), v.transform((value) => value.replace(/\s+/g, ' ')), v.minLength(QUESTION_MIN, QUESTION_MESSAGES.short), v.maxLength(QUESTION_MAX, QUESTION_MESSAGES.long)),
		reference: v.pipe(v.optional(v.string(), ''), v.trim(), v.minLength(1, QUESTION_MESSAGES.reference), v.maxLength(REFERENCE_MAX, QUESTION_MESSAGES.reference))
	}),
	async ({ question, reference }): Promise<ManualAnswer & { question: string }> => {
		if (!isAssistanceEnabled()) error(404, 'Page introuvable');
		const event = getRequestEvent();
		if (!event.locals.viewer.authenticated) invalid(QUESTION_MESSAGES.login);
		try {
			return { question, ...(await askManualQuestion(sylius(event), question, reference)) };
		} catch (cause) {
			if (!(cause instanceof ApiError)) throw cause;
			if (cause.status === 404) error(404, 'Page introuvable');
			if (cause.status === 503) invalid(QUESTION_MESSAGES.unavailable);
			if (cause.status < 500) invalid(cause.message);
			throw cause;
		}
	}
);
