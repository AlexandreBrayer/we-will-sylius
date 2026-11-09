// Contact page (/contact): message e-mailed to Yack by POST /api/v2/shop/yack/contact-requests (202, nothing stored).
// The rules (lengths, e-mail, limit per sender) are the API's; the form only maps its 422 / 429 answers.
import type { Viewer } from '#lib/types.js';
import { sylius, type ShopEvent } from './sylius.js';

export interface ContactMessage {
	name: string;
	email: string;
	company: string;
	phone: string;
	subject: string;
	message: string;
}

/** Same limit as ContactRequest::SUBJECT_MAX (Sylius). */
export const CONTACT_SUBJECT_MAX = 150;

/** API property → form field (422 violations). */
export const CONTACT_FIELDS: Record<string, keyof ContactMessage> = {
	name: 'name',
	email: 'email',
	company: 'company',
	phone: 'phone',
	subject: 'subject',
	message: 'message'
};

/** `?sujet=` of a link ("Nous contacter" of a CMS block…): one line, cut to the API limit. Svelte escapes it when shown. */
export function subjectFromQuery(raw: string | null): string {
	return (raw ?? '')
		.replace(/[\u0000-\u001f\u007f]+/g, ' ')
		.trim()
		.slice(0, CONTACT_SUBJECT_MAX);
}

export interface ContactForm {
	/** Values shown before any input. */
	defaults: Omit<ContactMessage, 'message'>;
	subjectMax: number;
}

/** Form of /contact: the logged-in customer's identity (viewer of the request), nothing for a visitor; subject from `?sujet=`. */
export function contactForm(viewer: Viewer, sujet: string | null): ContactForm {
	return {
		defaults: {
			name: [viewer.firstName, viewer.lastName].filter((part) => part && part.trim() !== '').join(' '),
			email: viewer.email ?? '',
			company: viewer.company ?? '',
			phone: viewer.phoneNumber ?? '',
			subject: subjectFromQuery(sujet)
		},
		subjectMax: CONTACT_SUBJECT_MAX
	};
}

export async function sendContactMessage(event: ShopEvent, message: ContactMessage): Promise<void> {
	const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim());
	await sylius(event).post<null>('/yack/contact-requests', {
		name: message.name.trim(),
		email: message.email.trim(),
		company: blankToNull(message.company),
		phone: blankToNull(message.phone),
		subject: blankToNull(message.subject),
		message: message.message.trim()
	});
}
