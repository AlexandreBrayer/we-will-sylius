import { requireCustomer } from '#lib/server/account.js';
import type { LayoutServerLoad } from './$types';

/** The whole /compte area needs a session: anonymous visitors go to /connexion?redirect=… */
export const load: LayoutServerLoad = (event) => {
	requireCustomer(event);
};
