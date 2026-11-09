// Card payment of placed orders (Sylius App\Yack\Payment, ADR 0009, docs/API_FRONT.md §9) and the
// simulated provider's hosted page. Remote adapters: #lib/remote/payment.remote.ts; the provider
// sends the customer back to routes/(shop)/commande/retour-paiement/[token]/+server.ts.
// No card number reaches the API: the hosted page sends the masked card only.
import { error, redirect } from '@sveltejs/kit';
import { orderLines, type Order, type OrderLine } from '#lib/order/types.js';
import type { MaskedCardInput } from '#lib/payment/card.js';
import type { CardAttempt, CardPaymentRedirect, SavedCard, SimulatedPaymentPage } from '#lib/payment/types.js';
import { requireCustomer } from './account.js';
import { ApiError, shopClient, sylius, type ShopEvent } from './sylius.js';

/** Pages of the front where the provider sends the customer (hosted page, return page). */
const FRONT_PATHS = /^\/(paiement-securise|commande\/retour-paiement)\//;

/**
 * Where to send the browser: a front page given as an absolute URL (Sylius knows the public front
 * URL) is kept on the host the customer uses; a real provider's page stays absolute.
 */
export function frontRedirect(url: string, event: Pick<ShopEvent, 'url'>): string {
	const target = new URL(url, event.url);
	return FRONT_PATHS.test(target.pathname) ? target.pathname + target.search : target.href;
}

const orderPath = (token: string) => `/yack/orders/${encodeURIComponent(token)}`;

/**
 * POST /shop/yack/orders/{token}/card-payment: session opened with the provider, `savedCardId`
 * for a one-click payment. Returns where to send the customer.
 */
export async function startCardPayment(event: ShopEvent, token: string, savedCardId: number | null): Promise<string> {
	const session = await sylius(event).post<CardPaymentRedirect>(`${orderPath(token)}/card-payment`, savedCardId ? { savedCardId } : {});
	return frontRedirect(session.redirectUrl, event);
}

/**
 * Page after the order is placed: the provider for a card order (confirmation once paid), the
 * confirmation page otherwise. A card session that cannot open leads to the payment page of the order.
 */
export async function afterOrderPlaced(event: ShopEvent, token: string, savedCardId: number | null): Promise<string> {
	const order = await sylius(event).get<Order>(`/orders/${encodeURIComponent(token)}`);
	if (!order.yack.paymentMethod?.card || !order.yack.payable) return `/commande/confirmation/${encodeURIComponent(token)}`;
	try {
		return await startCardPayment(event, token, savedCardId);
	} catch (e) {
		if (e instanceof ApiError && e.status < 500) return `/commande/reglement/${encodeURIComponent(token)}`;
		throw e;
	}
}

/** POST /shop/yack/orders/{token}/card-payment/result: customer back from the provider, outcome applied. */
export async function concludeCardPayment(event: ShopEvent, token: string): Promise<CardAttempt> {
	return sylius(event).post<CardAttempt>(`${orderPath(token)}/card-payment/result`);
}

/** Placed order of the customer (account or payment pages); 404 otherwise. */
export async function customerOrder(event: ShopEvent, token: string): Promise<Order> {
	if (!requireCustomer(event).canOrder) error(404, 'Commande introuvable');
	const order = await sylius(event).find<Order>(`/orders/${encodeURIComponent(token)}`);
	if (!order || order.checkoutState !== 'completed') error(404, 'Commande introuvable');
	return order;
}

export interface PayableOrderView {
	order: Order;
	lines: OrderLine[];
	savedCards: SavedCard[];
}

/**
 * Payment page of a placed order (`/commande/reglement/[token]`): last card attempt, saved cards,
 * methods to switch to. An order already paid goes to its confirmation page.
 */
export async function payableOrder(event: ShopEvent, token: string): Promise<PayableOrderView> {
	const order = await customerOrder(event, token);
	if (!order.yack.payable) redirect(303, `/commande/confirmation/${encodeURIComponent(token)}`);
	return { order, lines: orderLines(order), savedCards: await savedCards(event) };
}

/**
 * PATCH /shop/account/orders/{token}/payments/{id}: the order stays, its method changes (escompte,
 * VAT and amount recomputed by Sylius; the confirmation e-mail leaves with the bank details).
 */
export async function switchPaymentMethod(event: ShopEvent, token: string, method: string): Promise<void> {
	const order = await customerOrder(event, token);
	const payable = order.yack.payable;
	if (!payable) error(409, 'Cette commande est déjà réglée.');
	await sylius(event).patch(`/account/orders/${encodeURIComponent(token)}/payments/${payable.paymentId}`, { paymentMethod: method });
}

/** GET /shop/yack/saved-cards (newest first). */
export async function savedCards(event: ShopEvent): Promise<SavedCard[]> {
	return sylius(event).get<SavedCard[]>('/yack/saved-cards');
}

/** DELETE /shop/yack/saved-cards/{id}. */
export async function deleteSavedCard(event: ShopEvent, id: number): Promise<void> {
	await sylius(event).delete(`/yack/saved-cards/${id}`);
}

// --- Simulated provider (public, the session id is the secret) ----------------------------------

const SESSION_ID = /^[a-f0-9]{32}$/;
const simulator = (id: string) => `/yack/simulated-monext/sessions/${id}`;

/** GET /shop/yack/simulated-monext/sessions/{id}; 404 for an unknown / expired session. */
export async function simulatedPage(id: string): Promise<SimulatedPaymentPage> {
	const page = SESSION_ID.test(id) ? await shopClient(null).find<SimulatedPaymentPage>(simulator(id)) : null;
	if (!page) error(404, 'Session de paiement inconnue ou expirée');
	return page;
}

/** Card checked by the page: only its masked form goes to the provider's API. */
export async function submitSimulatedCard(id: string, card: MaskedCardInput, saveCard: boolean): Promise<SimulatedPaymentPage> {
	return shopClient(null).post<SimulatedPaymentPage>(`${simulator(id)}/card`, { ...card, saveCard });
}

export async function answerSimulatedChallenge(id: string, code: string): Promise<SimulatedPaymentPage> {
	return shopClient(null).post<SimulatedPaymentPage>(`${simulator(id)}/challenge`, { code });
}

export async function cancelSimulatedPayment(id: string): Promise<SimulatedPaymentPage> {
	return shopClient(null).post<SimulatedPaymentPage>(`${simulator(id)}/cancel`);
}
