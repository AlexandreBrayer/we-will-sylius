import { cartSummary } from '#lib/server/cart.js';
import { publishedPages } from '#lib/server/catalog.js';
import { sylius } from '#lib/server/sylius.js';
import type { LayoutServerLoad } from './$types';

/**
 * Viewer (profile, canSeePrices, canOrder) for every page, plus the data of the shop chrome
 * (header cart widget, footer CMS pages). The builder (/cms-admin) has its own chrome.
 * Also used by the error page, so unknown URLs keep the shop header and footer.
 */
export const load: LayoutServerLoad = async (event) => {
	const { viewer } = event.locals;
	if (event.route.id?.startsWith('/cms-admin')) return { viewer, chrome: null };

	const [pages, cart] = await Promise.all([publishedPages(sylius(event)), cartSummary(event)]);

	return {
		viewer,
		chrome: {
			cart,
			footerPages: pages.filter((page) => page.slug !== 'accueil'),
			servicesPage: pages.some((page) => page.slug === 'nos-services')
		}
	};
};
