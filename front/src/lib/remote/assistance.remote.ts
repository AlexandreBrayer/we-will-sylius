// Manuels et notices (/assistance) : recherche des documents d'un appareil et lecture de la photo de sa plaque.
// Appels : #lib/server/assistance.ts.
import { error, invalid } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { ManualsPage, PlateCheck } from '#lib/assistance/types.js';
import { PHOTO_MESSAGES, photoIssue } from '#lib/sav/nameplate.js';
import { findManuals, isAssistanceEnabled, readPlatePhoto } from '#lib/server/assistance.js';
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
