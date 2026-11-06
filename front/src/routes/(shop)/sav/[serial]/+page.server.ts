import { redirect } from '@sveltejs/kit';
import { findSerial, savBreadcrumb } from '#lib/server/sav.js';
import { sylius } from '#lib/server/sylius.js';
import { savKey, savUrl } from '#lib/sav/types.js';
import type { PageServerLoad } from './$types';

/** Warranty and models of a serial number (or of a ServiceCode); "not found" message otherwise. */
export const load: PageServerLoad = async (event) => {
	const query = event.params.serial;
	const result = await findSerial(sylius(event), query);
	if (!result) return { query, result: null, breadcrumb: savBreadcrumb(query) };

	// One URL per device: `yk19-kaz35s-004567` → /sav/YK19KAZ35S004567.
	const key = savKey(result);
	if (key !== query) redirect(308, savUrl(key));

	return { query: key, result, breadcrumb: savBreadcrumb(key) };
};
