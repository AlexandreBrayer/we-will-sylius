// Disposition de l'onglet Style du panneau du bloc (maquette front/static/ui-kit/panneau.html) : trois groupes,
// « Couleurs », « Titre », « Mise en page », et dans chacun l'ordre de la maquette. Le registre déclare les champs
// communs (STYLE_TRAITS) puis ceux de certains blocs (balise, alignement vertical) : le panneau les range ici,
// sans changer ni les champs ni le style enregistré. Module pur, testé par test/panel-layout.test.ts.
import type { StyleProperty } from '../../blocks/style.ts';

export type StyleGroupId = 'couleurs' | 'titre' | 'mise-en-page';

export interface StyleGroup {
	id: StyleGroupId;
	title: string;
}

export const STYLE_GROUPS: readonly StyleGroup[] = [
	{ id: 'couleurs', title: 'Couleurs' },
	{ id: 'titre', title: 'Titre' },
	{ id: 'mise-en-page', title: 'Mise en page' }
];

/** Ordre de la maquette ; un champ absent de la liste suit, à sa place d'origine, dans « Mise en page ». */
const ORDER: readonly StyleProperty[] = [
	'fond',
	'texte',
	'accent',
	'balise',
	'titre',
	'hautEspace',
	'basEspace',
	'gaucheEspace',
	'droiteEspace',
	'largeur',
	'alignement',
	'alignementVertical',
	'arrondi'
];

const GROUP_OF: Partial<Record<string, StyleGroupId>> = {
	fond: 'couleurs',
	texte: 'couleurs',
	accent: 'couleurs',
	balise: 'titre',
	titre: 'titre'
};

/** Groupe d'un champ de l'onglet Style d'après sa propriété de style (`styleProperty` du registre). */
export function styleGroup(property: string | undefined): StyleGroupId {
	return (property !== undefined ? GROUP_OF[property] : undefined) ?? 'mise-en-page';
}

/** Les champs de l'onglet Style rangés par groupe, dans l'ordre de la maquette ; les groupes vides sont omis. */
export function arrangeStyle<T>(items: readonly T[], property: (item: T) => string | undefined): { group: StyleGroup; items: T[] }[] {
	const rank = (item: T, index: number): number => {
		const found = ORDER.indexOf(property(item) as StyleProperty);
		return found >= 0 ? found : ORDER.length + index;
	};
	const ranked = items.map((item, index) => ({ item, rank: rank(item, index) })).sort((a, b) => a.rank - b.rank);
	return STYLE_GROUPS.map((group) => ({
		group,
		items: ranked.filter(({ item }) => styleGroup(property(item)) === group.id).map(({ item }) => item)
	})).filter((entry) => entry.items.length > 0);
}
