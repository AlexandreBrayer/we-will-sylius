// Cache mémoire « périmé pendant la mise à jour » pour des données publiques qui changent rarement
// (arbre du catalogue). Le premier appel attend le chargement ; ensuite, une valeur expirée est encore
// servie pendant qu'un seul rechargement tourne en arrière-plan : aucun visiteur n'attend la mise à jour.
// Un rechargement en échec garde l'ancienne valeur, l'erreur est journalisée (`onRefreshError`) et un
// nouvel essai part à l'appel suivant ; sans ancienne valeur, l'échec remonte et rien n'est gardé.

export interface StaleCache<T> {
	get(): Promise<T>;
}

export interface StaleCacheOptions {
	now?: () => number;
	/** Échec d'un rechargement en arrière-plan (l'ancienne valeur reste servie). */
	onRefreshError?: (error: unknown) => void;
}

export function staleCache<T>(load: () => Promise<T>, ttlMs: number, options: StaleCacheOptions = {}): StaleCache<T> {
	const now = options.now ?? Date.now;
	const onRefreshError =
		options.onRefreshError ?? ((error: unknown) => console.warn('Cache : rechargement en arrière-plan en échec, ancienne valeur gardée.', error));
	let value: { data: T; loadedAt: number } | null = null;
	let pending: Promise<T> | null = null;

	function refresh(): Promise<T> {
		pending ??= load().then(
			(data) => {
				value = { data, loadedAt: now() };
				pending = null;
				return data;
			},
			(error: unknown) => {
				pending = null;
				throw error;
			}
		);
		return pending;
	}

	return {
		get() {
			if (value === null) return refresh();
			if (now() - value.loadedAt > ttlMs && pending === null) refresh().catch(onRefreshError);
			return Promise.resolve(value.data);
		}
	};
}
