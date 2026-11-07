// Contact page (/contact): one form, message e-mailed to Yack. Logic and API mapping: #lib/server/contact.ts.
import { invalid } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { violationIssues } from '#lib/server/account.js';
import { CONTACT_FIELDS, CONTACT_SUBJECT_MAX, contactForm, sendContactMessage, type ContactForm } from '#lib/server/contact.js';
import { ApiError } from '#lib/server/sylius.js';

const text = v.optional(v.string(), '');
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Prefill of /contact: identity of the logged-in customer, subject = `?sujet=` of the page (passed as argument: a query cannot read the page URL). */
export const getContactForm = query(v.string(), (sujet): ContactForm => contactForm(getRequestEvent().locals.viewer, sujet));

/**
 * POST /shop/yack/contact-requests. Works without JavaScript (native POST, SSR re-render with the result).
 * Result `{ sent: true }`; 422 violations go to their field, 429 (too many messages) to the whole form.
 */
export const sendContact = form(
	v.object({
		name: v.pipe(text, v.trim(), v.nonEmpty('Indiquez votre nom.'), v.maxLength(120, 'Votre nom ne doit pas dépasser 120 caractères.')),
		email: v.pipe(text, v.trim(), v.regex(EMAIL, 'Saisissez une adresse e-mail valide.')),
		company: v.pipe(text, v.trim()),
		phone: v.pipe(text, v.trim()),
		subject: v.pipe(text, v.trim(), v.maxLength(CONTACT_SUBJECT_MAX, `Le sujet ne doit pas dépasser ${CONTACT_SUBJECT_MAX} caractères.`)),
		message: v.pipe(text, v.trim(), v.minLength(10, 'Votre message doit comporter au moins 10 caractères.'))
	}),
	async (message): Promise<{ sent: true }> => {
		try {
			await sendContactMessage(getRequestEvent(), message);
		} catch (error) {
			if (!(error instanceof ApiError) || error.status >= 500) throw error;
			invalid(...(error.status === 422 && error.violations.length > 0 ? violationIssues(error.violations, CONTACT_FIELDS) : [error.message]));
		}
		return { sent: true };
	}
);
