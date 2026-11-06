// Customer account shapes of the shop API (docs/API_FRONT.md §2, §5, §6, §9).
import type { Order } from '#lib/order/types.js';

/** GET /shop/customers/{id}. */
export interface Customer {
	email: string;
	firstName: string | null;
	lastName: string | null;
	fullName: string | null;
	phoneNumber: string | null;
	/** IRI of the default (billing) address. */
	defaultAddress: string | null;
}

/** GET /shop/countries (channel countries). */
export interface Country {
	code: string;
	name: string;
}

/** GET /shop/yack/order-access-requests/draft — form prefilled from the account. */
export interface OrderAccessRequestDraft {
	company: string | null;
	siret: string | null;
	fullName: string | null;
	email: string | null;
	phone: string | null;
	address: string | null;
	message: string | null;
}

/** GET /shop/yack/order-access-requests (latest first). */
export interface OrderAccessRequestView extends OrderAccessRequestDraft {
	id: number;
	status: 'pending' | 'accepted' | 'refused';
	statusLabel: string;
	createdAt: string;
	decidedAt: string | null;
}

/** Item of GET /shop/orders (history): native fields + yack block. */
export type OrderSummary = Pick<Order, 'tokenValue' | 'number' | 'state' | 'checkoutCompletedAt' | 'itemsTotal' | 'yack'>;
