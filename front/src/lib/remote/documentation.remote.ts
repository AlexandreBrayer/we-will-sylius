// Documentation assistant, product page. Logic and API mapping: #lib/server/documentation.ts.
import { invalid } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { QUESTION_MAX, QUESTION_MIN, UNAVAILABLE, type DocumentationAnswer, type DocumentationCoverage } from '#lib/documentation/types.js';
import { askDocumentation as ask, documentationCoverage } from '#lib/server/documentation.js';
import { ApiError } from '#lib/server/sylius.js';

/** Notices and sample question of a product (null for a visitor). */
export const getDocumentationCoverage = query(v.string(), (productCode): Promise<DocumentationCoverage | null> =>
	documentationCoverage(getRequestEvent(), productCode)
);

/**
 * Question to the notices of the product. Works without JavaScript (native POST, SSR re-render).
 * Result: the answer with its sources and the question asked; 422 / 503 messages go to the form.
 */
export const askDocumentation = form(
	v.object({
		question: v.pipe(
			v.optional(v.string(), ''),
			v.trim(),
			v.minLength(QUESTION_MIN, `Votre question doit comporter au moins ${QUESTION_MIN} caractères.`),
			v.maxLength(QUESTION_MAX, `Votre question ne doit pas dépasser ${QUESTION_MAX} caractères.`)
		),
		productCode: v.pipe(v.optional(v.string(), ''), v.trim(), v.maxLength(64))
	}),
	async ({ question, productCode }): Promise<DocumentationAnswer & { question: string }> => {
		const event = getRequestEvent();
		if (!event.locals.viewer.authenticated) invalid('Connectez-vous pour poser une question sur la documentation.');
		try {
			return { question, ...(await ask(event, question, productCode)) };
		} catch (error) {
			if (!(error instanceof ApiError)) throw error;
			if (error.status < 500) invalid(error.message);
			// 503 et toute autre erreur serveur : le message d'indisponibilité, jamais un envoi sans suite visible.
			if (error.status !== 503) console.warn(`Assistant documentation : l'API répond ${error.status}`);
			invalid(UNAVAILABLE);
		}
	}
);
