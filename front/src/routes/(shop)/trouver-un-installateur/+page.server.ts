import { sylius } from '#lib/server/sylius.js';
import type { InstallerFilters, InstallerSearch } from '#lib/installers/types.js';
import type { PageServerLoad } from './$types';

/** Installer directory filtered by ?secteur=&gamme=&activite= (same parameters as the Twig page). */
export const load: PageServerLoad = async (event) => {
	const { searchParams } = event.url;
	const api = sylius(event);
	const [filters, search] = await Promise.all([
		api.get<InstallerFilters>('/yack/installers/filters'),
		api.get<InstallerSearch>('/yack/installers', {
			sector: searchParams.get('secteur'),
			range: searchParams.get('gamme'),
			activity: searchParams.get('activite')
		})
	]);

	return { filters, ...search };
};
