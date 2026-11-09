// Installer directory: GET /api/v2/shop/yack/installers[/filters] (docs/API_FRONT.md §8).

export interface Activity {
	code: string;
	label: string;
}

export interface Installer {
	name: string;
	city: string;
	postcode: string;
	sector: string;
	ranges: string[];
	activities: Activity[];
	latitude: number;
	longitude: number;
	phone: string | null;
	certifiedRge: boolean;
}

export interface InstallerCriteria {
	sector: string | null;
	range: string | null;
	activity: string | null;
}

export interface InstallerFilters {
	sectors: string[];
	ranges: string[];
	activities: Activity[];
}

export interface InstallerSearch {
	/** Filters applied (an unknown value is ignored by the API and comes back null). */
	criteria: InstallerCriteria;
	count: number;
	installers: Installer[];
}

/** Page URL query `?secteur=&gamme=&activite=` → `getInstallers` argument (empty values dropped). */
export function installerSearch(url: { searchParams: Pick<URLSearchParams, 'get'> }): { secteur?: string; gamme?: string; activite?: string } {
	const search: { secteur?: string; gamme?: string; activite?: string } = {};
	for (const name of ['secteur', 'gamme', 'activite'] as const) {
		const value = url.searchParams.get(name);
		if (value) search[name] = value;
	}
	return search;
}
