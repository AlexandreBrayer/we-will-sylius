import { latestProducts } from '#lib/server/catalog.js';
import { loadCmsPage } from '#lib/server/cms.js';
import { sylius } from '#lib/server/sylius.js';
import type { PageServerLoad } from './$types';

/** Homepage = CMS page "accueil" (static hero while it is not published) + "Derniers produits". */
export const load: PageServerLoad = async (event) => {
	const [cms, latest] = await Promise.all([
		loadCmsPage(event, 'accueil'),
		latestProducts(sylius(event), event.locals.viewer)
	]);

	return { cms, latest };
};
