declare global {
	namespace App {
		interface Locals {
			/** JWT of the Sylius administrator (cookie set by /admin/login), null when logged out. */
			adminToken: string | null;
		}
	}
}

export {};
