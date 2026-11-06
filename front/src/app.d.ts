import type { Viewer } from '#lib/types.js';

declare global {
	namespace App {
		interface Locals {
			/** Shop JWT of the customer (cookie yack_session), null for a visitor. */
			sessionToken: string | null;
			/** tokenValue of the ECOM cart (cookie yack_cart), null when none yet. */
			cartToken: string | null;
			/** Who is browsing (GET /api/v2/shop/yack/me), loaded by hooks.server.ts. */
			viewer: Viewer;
			/** JWT of the Sylius administrator using the builder (cookie yack_cms_token). */
			adminToken: string | null;
		}
	}
}

export {};
