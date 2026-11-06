// Contract shared with Sylius (App\Yack\Content\Domain\BlockType / PageBlocks).

export type BlockType =
	| 'hero'
	| 'texte-image'
	| 'produits'
	| 'categories'
	| 'cta'
	| 'faq'
	| 'reassurance';

export type BlockProps = Record<string, string>;

export interface PageBlock {
	type: BlockType;
	props: BlockProps;
}

export type PageStatus = 'draft' | 'published';

export interface CmsPage {
	slug: string;
	title: string;
	metaTitle: string | null;
	metaDescription: string | null;
	status: PageStatus;
	blocks: PageBlock[];
	grapesData: Record<string, unknown> | null;
	updatedAt: string;
}

export interface CmsPageSummary {
	slug: string;
	title: string;
	status?: PageStatus;
	updatedAt: string;
}

export interface ProductCard {
	code: string;
	name: string;
	url: string;
	image: string | null;
}

export interface TaxonCard {
	code: string;
	name: string;
	url: string;
}

export interface Catalog {
	products: Record<string, ProductCard>;
	taxons: Record<string, TaxonCard>;
}
