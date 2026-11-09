// Pills of the "categorie-sous-categories" block (#lib/blocks/bound/TaxonChildren.svelte): the families
// of the current category. No Svelte here: `npm test`.
import type { TaxonCard } from './types.js';

export interface FamilyPill {
	code: string;
	name: string;
	url: string;
	/** The page shown: active and checked. */
	current: boolean;
	/** First pill: the whole level ("Toutes les pièces"). */
	all: boolean;
}

/** Label of the first pill when the block leaves it empty. */
export const ALL_LABEL = 'Tout voir';

/**
 * A category with sub-categories shows them, after an "all" pill for itself (active). A category
 * without sub-categories shows its siblings, itself active, after an "all" pill that leads to its
 * parent. Nothing at the top of a section without sub-categories.
 */
export function familyPills(
	taxon: { taxon: { code: string; url: string }; children: TaxonCard[]; siblings: TaxonCard[]; up: string | null },
	allLabel = ''
): FamilyPill[] {
	const label = allLabel.trim() || ALL_LABEL;
	const pill = (card: TaxonCard, current: boolean): FamilyPill => ({ code: card.code, name: card.name, url: card.url, current, all: false });

	if (taxon.children.length > 0) {
		return [
			{ code: taxon.taxon.code, name: label, url: taxon.taxon.url, current: true, all: true },
			...taxon.children.map((child) => pill(child, false))
		];
	}
	if (taxon.up !== null && taxon.siblings.length > 0) {
		return [
			{ code: '', name: label, url: taxon.up, current: false, all: true },
			...taxon.siblings.map((sibling) => pill(sibling, sibling.code === taxon.taxon.code))
		];
	}
	return [];
}
