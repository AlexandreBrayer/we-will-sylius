// Installer directory (/trouver-un-installateur): GET /api/v2/shop/yack/installers[/filters] (public).
import { getRequestEvent, query } from '$app/server';
import * as v from 'valibot';
import type { InstallerFilters, InstallerSearch } from '#lib/installers/types.js';
import { sylius } from '#lib/server/sylius.js';

/** Filter options (sectors, ranges, activities). */
export const getInstallerFilters = query(
	(): Promise<InstallerFilters> => sylius(getRequestEvent()).get<InstallerFilters>('/yack/installers/filters')
);

/**
 * Installers matching the page URL query `?secteur=&gamme=&activite=` (`installerSearch(page.url)`,
 * #lib/installers/types.ts); unknown values are ignored by the API (criteria back to null).
 */
export const getInstallers = query(
	v.object({ secteur: v.optional(v.string()), gamme: v.optional(v.string()), activite: v.optional(v.string()) }),
	({ secteur, gamme, activite }): Promise<InstallerSearch> =>
		sylius(getRequestEvent()).get<InstallerSearch>('/yack/installers', {
			sector: secteur ?? null,
			range: gamme ?? null,
			activity: activite ?? null
		})
);
