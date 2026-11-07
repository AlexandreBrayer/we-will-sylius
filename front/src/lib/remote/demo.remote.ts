// Barre de démonstration (DEMO-01) : bascule de profil sur les comptes de démo, logique dans #lib/server/demo.ts.
import { error, redirect } from '@sveltejs/kit';
import { form, getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import { attachCart } from '#lib/server/cart.js';
import { DEMO_ACCOUNTS, DEMO_PASSWORD, demoBarEnabled } from '#lib/server/demo.js';
import { endSession, safeRedirect, startSession } from '#lib/server/session.js';
import { customerToken, jwtExpiry, shopClient } from '#lib/server/sylius.js';
import type { Viewer } from '#lib/types.js';

/** La barre est-elle active (YACK_DEMO_BAR=1) ? */
export const getDemoBar = query((): boolean => demoBarEnabled());

/** Visiteur : fin de session ; PRO ou ECOM : connexion au compte de démo, puis retour à la page courante. */
export const switchProfile = form(
	v.object({ profile: v.picklist(['visiteur', 'pro', 'ecom']), back: v.optional(v.string(), '/') }),
	async ({ profile, back }) => {
		if (!demoBarEnabled()) error(404, 'Introuvable');
		const event = getRequestEvent();

		if (profile === 'visiteur') {
			endSession(event.cookies);
		} else {
			const token = await customerToken(DEMO_ACCOUNTS[profile], DEMO_PASSWORD);
			startSession(event.cookies, event.url, token, jwtExpiry(token), false);
			const api = shopClient(token);
			const viewer = await api.get<Viewer>('/yack/me');
			event.locals.sessionToken = token;
			event.locals.viewer = viewer;
			if (viewer.canOrder) await attachCart(api, event);
		}

		redirect(303, safeRedirect(back));
	}
);
