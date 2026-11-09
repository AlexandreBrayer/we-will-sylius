// Customer account (/compte/…): identity and profile, address book, order history (ECOM) and the
// PRO → ECOM "commander en ligne" request. Every function needs a session (`requireCustomer`, which
// sends a visitor to /connexion); the /compte layout guard does it before the page renders.
import { error, invalid, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { Country, Customer, OrderAccessRequestDraft, OrderAccessRequestView, OrderSummary } from '#lib/account/types.js';
import type { Order, SavedAddress } from '#lib/order/types.js';
import { customerOrders, requireCustomer, violationIssues } from '#lib/server/account.js';
import { ApiError, sylius } from '#lib/server/sylius.js';
import type { Viewer } from '#lib/types.js';
import { getViewer } from './session.remote.js';

const text = v.optional(v.string(), '');

/** Countries of the channel (GET /shop/countries): address forms. */
export const getCountries = query((): Promise<Country[]> => sylius(getRequestEvent()).get<Country[]>('/countries'));

/** GET /shop/customers/{id}: name, e-mail, phone, default address IRI. */
export const getCustomer = query((): Promise<Customer> => {
	const event = getRequestEvent();
	return sylius(event).get<Customer>(`/customers/${requireCustomer(event).customerId}`);
});

const PHONE = /^\+?[0-9 .()-]{10,20}$/;

/**
 * "Informations personnelles": PUT /shop/customers/{id} (name, phone). The e-mail is the login (and the
 * JWT subject): shown, not edited, so the session stays valid. Result `{ saved }`.
 */
export const updateProfile = form(
	v.object({
		firstName: v.pipe(text, v.trim(), v.nonEmpty('Indiquez votre prénom.')),
		lastName: v.pipe(text, v.trim(), v.nonEmpty('Indiquez votre nom.')),
		phoneNumber: v.pipe(
			text,
			v.trim(),
			v.check((phone) => phone === '' || PHONE.test(phone), 'Numéro de téléphone invalide (10 chiffres attendus).')
		)
	}),
	async ({ firstName, lastName, phoneNumber }) => {
		const event = getRequestEvent();
		const api = sylius(event);
		try {
			await api.put(`/customers/${requireCustomer(event).customerId}`, { firstName, lastName, phoneNumber: phoneNumber || null });
		} catch (e) {
			if (e instanceof ApiError && e.status === 422) {
				invalid(...violationIssues(e.violations, { firstName: 'firstName', lastName: 'lastName', phoneNumber: 'phoneNumber' }));
			}
			throw e;
		}
		// The viewer (name, phone: dashboard, header) was read before the update.
		event.locals.viewer = await api.get<Viewer>('/yack/me');
		await Promise.all([getCustomer().refresh(), getViewer().refresh()]);
		return { saved: true };
	}
);

/** "Changer votre mot de passe": PUT /shop/customers/{id}/password. Result `{ saved }`. "_" fields are never sent back to the page. */
export const changePassword = form(
	v.pipe(
		v.object({
			_currentPassword: v.pipe(text, v.nonEmpty('Saisissez votre mot de passe actuel.')),
			_newPassword: v.pipe(text, v.minLength(8, 'Le mot de passe doit comporter au moins 8 caractères.')),
			_confirmNewPassword: text
		}),
		v.forward(
			v.partialCheck(
				[['_newPassword'], ['_confirmNewPassword']],
				(input) => input._newPassword === input._confirmNewPassword,
				'Les deux mots de passe ne correspondent pas.'
			),
			['_confirmNewPassword']
		)
	),
	async ({ _currentPassword, _newPassword, _confirmNewPassword }) => {
		const event = getRequestEvent();
		try {
			await sylius(event).put(`/customers/${requireCustomer(event).customerId}/password`, {
				currentPassword: _currentPassword,
				newPassword: _newPassword,
				confirmNewPassword: _confirmNewPassword
			});
		} catch (e) {
			if (e instanceof ApiError && e.status === 422) {
				invalid(
					...violationIssues(e.violations, {
						currentPassword: '_currentPassword',
						newPassword: '_newPassword',
						confirmNewPassword: '_confirmNewPassword'
					})
				);
			}
			throw e;
		}
		return { saved: true };
	}
);

/** Address book: GET /shop/addresses, the default one (customer.defaultAddress, used for billing) apart. */
export const getAddressBook = query(async (): Promise<{ defaultAddress: SavedAddress | null; addresses: SavedAddress[] }> => {
	const event = getRequestEvent();
	const { customerId } = requireCustomer(event);
	const api = sylius(event);
	const [addresses, customer] = await Promise.all([api.get<SavedAddress[]>('/addresses'), api.get<Customer>(`/customers/${customerId}`)]);
	const defaultId = customer.defaultAddress ? Number(customer.defaultAddress.split('/').pop()) : null;
	return {
		defaultAddress: addresses.find((address) => address.id === defaultId) ?? null,
		addresses: addresses.filter((address) => address.id !== defaultId)
	};
});

/** Address of the book (/compte/adresses/[id]); 404 when unknown. */
export const getAddress = query(v.string(), async (id): Promise<SavedAddress> => {
	const event = getRequestEvent();
	requireCustomer(event);
	const address = /^\d+$/.test(id) ? await sylius(event).find<SavedAddress>(`/addresses/${id}`) : null;
	if (!address) error(404, 'Adresse introuvable');
	return address;
});

const ADDRESS_FIELDS = ['firstName', 'lastName', 'company', 'street', 'postcode', 'city', 'countryCode', 'phoneNumber'] as const;

/**
 * Address form: POST /shop/addresses, or PUT /shop/addresses/{id} when `id` is an address id
 * (`saveAddress.for(id)`; `.for('nouvelle')` creates). API 422 → field issues; then /compte/adresses?enregistree=1.
 */
export const saveAddress = form(
	v.object({
		id: text,
		firstName: v.pipe(text, v.trim()),
		lastName: v.pipe(text, v.trim()),
		company: v.pipe(text, v.trim()),
		street: v.pipe(text, v.trim()),
		postcode: v.pipe(text, v.trim()),
		city: v.pipe(text, v.trim()),
		countryCode: v.pipe(text, v.trim()),
		phoneNumber: v.pipe(text, v.trim())
	}),
	async ({ id, ...address }) => {
		const event = getRequestEvent();
		requireCustomer(event);
		const api = sylius(event);
		const body = { ...address, company: address.company || null, phoneNumber: address.phoneNumber || null };
		try {
			if (/^\d+$/.test(id)) await api.put(`/addresses/${id}`, body);
			else await api.post('/addresses', body);
		} catch (e) {
			if (e instanceof ApiError && e.status === 422) {
				invalid(...violationIssues(e.violations, Object.fromEntries(ADDRESS_FIELDS.map((field) => [field, field]))));
			}
			if (e instanceof ApiError && e.status === 404) error(404, 'Adresse introuvable');
			throw e;
		}
		redirect(303, '/compte/adresses?enregistree=1');
	}
);

/**
 * Buttons of the address book list (one form, the button carries `intent`): `defaut:{id}` → PUT
 * /shop/customers/{id} {defaultAddress}, `supprimer:{id}` → DELETE /shop/addresses/{id}. Result `{ message }`.
 */
export const manageAddress = form(
	v.object({ intent: v.pipe(text, v.regex(/^(defaut|supprimer):\d+$/, 'Adresse inconnue.')) }),
	async ({ intent }) => {
		const event = getRequestEvent();
		const { customerId } = requireCustomer(event);
		const api = sylius(event);
		const [action, id] = intent.split(':');
		try {
			if (action === 'defaut') await api.put(`/customers/${customerId}`, { defaultAddress: `/api/v2/shop/addresses/${id}` });
			else await api.delete(`/addresses/${id}`);
		} catch (e) {
			if (e instanceof ApiError && e.status < 500) invalid(e.message);
			throw e;
		}
		if (action === 'supprimer') {
			await getAddressBook().refresh();
			return { message: 'Adresse supprimée.' };
		}
		// The billing address of the viewer (dashboard, checkout) is the default one.
		event.locals.viewer = await api.get<Viewer>('/yack/me');
		await Promise.all([getAddressBook().refresh(), getViewer().refresh()]);
		return { message: 'Adresse par défaut mise à jour.' };
	}
);

/** Dashboard (ECOM): the 3 latest orders and the order count; null for an account that cannot order (PRO). */
export const getRecentOrders = query(async (): Promise<{ orders: OrderSummary[]; total: number } | null> => {
	const event = getRequestEvent();
	if (!requireCustomer(event).canOrder) return null;
	const orders = await customerOrders(sylius(event));
	return { orders: orders.slice(0, 3), total: orders.length };
});

const PER_PAGE = 10;

/** Order history (ECOM), `page` = ?page of the URL; null for an account that cannot order (PRO). */
export const getOrders = query(
	v.optional(v.string()),
	async (requested): Promise<{ orders: OrderSummary[]; page: number; pages: number; total: number } | null> => {
		const event = getRequestEvent();
		if (!requireCustomer(event).canOrder) return null;
		const orders = await customerOrders(sylius(event));
		const pages = Math.max(1, Math.ceil(orders.length / PER_PAGE));
		const page = Math.min(Math.max(1, Number(requested) || 1), pages);
		return { orders: orders.slice((page - 1) * PER_PAGE, page * PER_PAGE), page, pages, total: orders.length };
	}
);

/**
 * Order detail by number (link of the confirmation / shipment e-mails); null for an account that cannot
 * order (PRO), 404 when not one of the customer's. The API loads an order by tokenValue: it is found in the history.
 */
export const getOrder = query(v.string(), async (number): Promise<Order | null> => {
	const event = getRequestEvent();
	if (!requireCustomer(event).canOrder) return null;
	const api = sylius(event);
	const summary = (await customerOrders(api)).find((order) => order.number === number);
	if (!summary) error(404, 'Commande introuvable');
	return api.get<Order>(`/orders/${encodeURIComponent(summary.tokenValue)}`);
});

/**
 * "Commander en ligne" (PRO): form prefilled from the account (GET /yack/order-access-requests/draft) and the
 * latest request (pending / refused). `canRequest` false: the account already orders online.
 */
export const getOrderAccess = query(
	async (): Promise<{ canRequest: boolean; draft: OrderAccessRequestDraft | null; latest: OrderAccessRequestView | null }> => {
		const event = getRequestEvent();
		if (!requireCustomer(event).canRequestOrderAccess) return { canRequest: false, draft: null, latest: null };
		const api = sylius(event);
		const [draft, requests] = await Promise.all([
			api.get<OrderAccessRequestDraft>('/yack/order-access-requests/draft'),
			api.get<OrderAccessRequestView[]>('/yack/order-access-requests')
		]);
		return { canRequest: true, draft, latest: requests[0] ?? null };
	}
);

const ACCESS_FIELDS = ['company', 'siret', 'fullName', 'email', 'phone', 'address', 'message'] as const;

/**
 * Order access request: POST /shop/yack/order-access-requests (201; 409 when one is already pending; 422
 * → field issues), then /compte/acces-commande?envoyee=1 (the redirect reloads the viewer: pending state).
 */
export const requestOrderAccess = form(
	v.object({
		company: v.pipe(text, v.trim()),
		siret: v.pipe(text, v.trim()),
		fullName: v.pipe(text, v.trim()),
		email: v.pipe(text, v.trim()),
		phone: v.pipe(text, v.trim()),
		address: v.pipe(text, v.trim()),
		message: v.pipe(text, v.trim())
	}),
	async (request) => {
		const event = getRequestEvent();
		requireCustomer(event);
		try {
			await sylius(event).post('/yack/order-access-requests', { ...request, message: request.message || null });
		} catch (e) {
			if (!(e instanceof ApiError) || e.status >= 500) throw e;
			invalid(...(e.status === 422 ? violationIssues(e.violations, Object.fromEntries(ACCESS_FIELDS.map((field) => [field, field]))) : [e.message]));
		}
		redirect(303, '/compte/acces-commande?envoyee=1');
	}
);
