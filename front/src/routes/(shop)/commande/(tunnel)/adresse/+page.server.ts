import { fail, redirect } from '@sveltejs/kit';
import type { OrderAddress, SavedAddress } from '#lib/order/types.js';
import { apiFailure, checkoutCart } from '#lib/server/checkout.js';
import { sylius, type ShopClient } from '#lib/server/sylius.js';
import type { Actions, PageServerLoad } from './$types';

const FIELDS = ['firstName', 'lastName', 'company', 'street', 'postcode', 'city', 'countryCode', 'phoneNumber'] as const;

const REQUIRED: Partial<Record<(typeof FIELDS)[number], string>> = {
	firstName: 'le prénom',
	lastName: 'le nom',
	street: "l'adresse (numéro et voie)",
	postcode: 'le code postal',
	city: 'la ville',
	countryCode: 'le pays'
};

const countryNames = new Intl.DisplayNames(['fr'], { type: 'region' });

/** Same address, whatever the phone formatting ("04 42 00 00 10" = "0442000010"). */
const addressKey = (a: OrderAddress): string =>
	[a.firstName, a.lastName, a.company ?? '', a.street, a.postcode, a.city, a.countryCode, (a.phoneNumber ?? '').replace(/\D/g, '')]
		.map((part) => part.trim().toLowerCase())
		.join('|');

const toAddress = (a: OrderAddress): OrderAddress => ({
	firstName: a.firstName,
	lastName: a.lastName,
	company: a.company || null,
	street: a.street,
	postcode: a.postcode,
	city: a.city,
	countryCode: a.countryCode,
	phoneNumber: a.phoneNumber || null
});

/** Address book of the customer, duplicates (same address saved by several orders) removed. */
async function addressBook(api: ShopClient): Promise<SavedAddress[]> {
	const seen = new Set<string>();
	return (await api.get<SavedAddress[]>('/addresses')).filter((address) => {
		const key = addressKey(address);
		if (seen.has(key)) return false;
		seen.add(key);
		return true;
	});
}

/** Pre-selection: the saved address equal to `current`, else "new" pre-filled with it, else the first saved one. */
function choice(book: SavedAddress[], current: OrderAddress | null): string {
	if (current) return String(book.find((a) => addressKey(a) === addressKey(current))?.id ?? 'new');
	return book.length > 0 ? String(book[0].id) : 'new';
}

export const load: PageServerLoad = async (event) => {
	const { order } = await event.parent();
	const api = sylius(event);
	const [book, countries] = await Promise.all([addressBook(api), api.get<{ code: string }[]>('/countries')]);

	// The API pre-fills the billing address with the company address of the account.
	const shipping = order.shippingAddress ?? order.billingAddress;
	const billing = order.billingAddress ?? shipping;

	return {
		book,
		countries: countries.map(({ code }) => ({ code, name: countryNames.of(code) ?? code })).sort((a, b) => a.name.localeCompare(b.name, 'fr')),
		shipping: { choice: choice(book, order.shippingAddress ?? (book.length > 0 ? null : shipping)), values: shipping },
		billing: { choice: choice(book, billing), values: billing },
		sameBilling: !order.billingAddress || !shipping || addressKey(order.billingAddress) === addressKey(shipping)
	};
};

type Values = Record<string, string>;

/** Address of a section of the form: a saved address (`<prefix>-choice` = id) or the typed one. */
function readAddress(form: FormData, prefix: string, book: SavedAddress[]): { address: OrderAddress | null; values: Values; error: string | null } {
	const values: Values = { choice: String(form.get(`${prefix}-choice`) ?? 'new') };
	for (const field of FIELDS) values[field] = String(form.get(`${prefix}-${field}`) ?? '').trim();

	if (values.choice !== 'new') {
		const saved = book.find((a) => String(a.id) === values.choice);
		return saved ? { address: toAddress(saved), values, error: null } : { address: null, values, error: "Cette adresse n'existe plus dans votre carnet." };
	}
	const missing = Object.entries(REQUIRED).filter(([field]) => !values[field]).map(([, label]) => label);
	if (missing.length > 0) return { address: null, values, error: `Indiquez ${missing.join(', ')}.` };
	return { address: toAddress(values as unknown as OrderAddress), values, error: null };
}

export const actions: Actions = {
	/** PUT /shop/orders/{token}: shipping + billing addresses (checkoutState → addressed). */
	default: async (event) => {
		const order = await checkoutCart(event);
		const api = sylius(event);
		const form = await event.request.formData();
		const book = await addressBook(api);
		const sameBilling = form.get('sameBilling') === '1';

		const shipping = readAddress(form, 'shipping', book);
		const billing = sameBilling ? shipping : readAddress(form, 'billing', book);
		const error = shipping.error ? `Adresse de livraison : ${shipping.error}` : billing.error ? `Adresse de facturation : ${billing.error}` : null;
		if (error || !shipping.address || !billing.address) {
			return fail(400, { error: error ?? 'Adresse incomplète.', shipping: shipping.values, billing: billing.values, sameBilling });
		}

		try {
			await api.put(`/orders/${order.tokenValue}`, {
				email: event.locals.viewer.email ?? order.customer?.email,
				shippingAddress: shipping.address,
				billingAddress: billing.address
			});
		} catch (e) {
			const failure = apiFailure(e);
			return fail(failure.status, { ...failure.data, shipping: shipping.values, billing: billing.values, sameBilling });
		}
		redirect(303, '/commande/livraison');
	}
};
