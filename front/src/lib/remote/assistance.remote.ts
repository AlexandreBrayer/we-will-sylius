// Manuels et notices (/assistance) : recherche des documents d'un appareil. Appels : #lib/server/assistance.ts.
import { error } from '@sveltejs/kit';
import { getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { ManualsPage } from '#lib/assistance/types.js';
import { findManuals, isAssistanceEnabled } from '#lib/server/assistance.js';
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
