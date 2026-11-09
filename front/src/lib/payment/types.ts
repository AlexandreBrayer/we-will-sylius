// Card payment (Sylius App\Yack\Payment, ADR 0009): never a card number, only the masked card.

/** Masked card: brand, last four digits, expiry. */
export interface MaskedCard {
	brand: 'visa' | 'mastercard' | 'amex' | 'cb';
	brandLabel: string;
	last4: string;
	/** "Visa •••• 4242" */
	label: string;
	/** "12/28" */
	expiry: string;
}

export type CardPaymentStatus = 'pending' | 'accepted' | 'refused' | 'cancelled';

/** Outcome of a card attempt (POST /shop/yack/orders/{token}/card-payment/result, order history). */
export interface CardAttempt {
	status: CardPaymentStatus;
	/** "Paiement refusé : fonds insuffisants" */
	message: string;
	reason: string | null;
	card: MaskedCard | null;
	threeDSecure: boolean;
	oneClick: boolean;
	transactionReference: string | null;
}

/** POST /shop/yack/orders/{token}/card-payment → where to send the customer. */
export interface CardPaymentRedirect {
	sessionId: string;
	redirectUrl: string;
}

/** GET /shop/yack/saved-cards ("Mes cartes", one-click payment). */
export interface SavedCard extends MaskedCard {
	id: number;
	expired: boolean;
	createdAt: string;
}

export type SimulatedStep = 'card' | 'challenge' | 'accepted' | 'refused' | 'cancelled';

/** GET /shop/yack/simulated-monext/sessions/{id}: hosted payment page of the simulated provider. */
export interface SimulatedPaymentPage {
	id: string;
	merchant: string;
	orderNumber: string;
	reference: string;
	/** Cents, taxes included. */
	amount: number;
	currencyCode: string;
	customerEmail: string;
	step: SimulatedStep;
	card: MaskedCard | null;
	oneClick: boolean;
	refusalReason: string | null;
	/** 3-D Secure code shown on the bank page (demo), null outside that step. */
	challengeCode: string | null;
	/** Yack page to go back to once the session is over. */
	returnUrl: string;
}
