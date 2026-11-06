import { publishedPages, syliusPublicUrl } from '$lib/server/sylius.js';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async () => ({
	syliusUrl: syliusPublicUrl(),
	footerPages: (await publishedPages()).filter((page) => page.slug !== 'accueil')
});
