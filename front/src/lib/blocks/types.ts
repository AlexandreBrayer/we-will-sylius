// Contract shared with Sylius (App\Yack\Content\Domain\BlockType / PageBlocks, docs/API_FRONT.md §11).
import type { Breadcrumb, ListingParams, ProductDetail, ProductPage, ProductSummary, SortKey, SortOption } from '#lib/types.js';
import type { BlockStyle } from './style.js';

/** Free blocks: allowed in pages and templates. */
export type FreeBlockType =
	| 'hero'
	| 'texte-image'
	| 'produits'
	| 'categories'
	| 'cta'
	| 'faq'
	| 'reassurance'
	| 'chiffres-cles'
	| 'acces-rapides'
	| 'documents'
	| 'tuiles'
	| 'accompagnement';

/** Blocks bound to the product of a product template (no data prop: they read the current product). */
export type ProductBlockType =
	| 'produit-galerie'
	| 'produit-infos'
	| 'produit-achat'
	| 'produit-caracteristiques'
	| 'produit-description'
	| 'produit-documents'
	| 'produit-sav'
	| 'produits-associes';

/** Blocks bound to the category of a category template. */
export type TaxonBlockType = 'categorie-entete' | 'categorie-produits' | 'categorie-sous-categories';

export type BlockType = FreeBlockType | ProductBlockType | TaxonBlockType;

export type BlockProps = Record<string, string>;

export interface PageBlock {
	type: BlockType;
	props: BlockProps;
	/**
	 * Named style (App\Yack\Content\Domain\BlockStyle). The API sends `[]` for an empty style and
	 * no key for a block saved before styles existed: read it through readStyle() / styleToCss().
	 */
	style?: BlockStyle;
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

export interface TaxonCard {
	code: string;
	name: string;
	url: string;
}

export interface Catalog {
	products: Record<string, ProductSummary>;
	taxons: Record<string, TaxonCard>;
}

// ── Templates (fiche produit, page catégorie) ──

export type TemplateType = 'product' | 'taxon';

/** Template applied to a product / category page (`GET /shop/cms-templates/resolve`). */
export interface ResolvedTemplate {
	id: number;
	type: TemplateType;
	name: string;
	/** product = assigned to the product, taxon = category (`sourceCode`) or ancestor, default. */
	source: 'product' | 'taxon' | 'default';
	sourceCode: string | null;
	blocks: PageBlock[];
	updatedAt: string;
}

export interface TemplateSummary {
	id: number;
	type: TemplateType;
	name: string;
	isDefault: boolean;
	status: PageStatus;
	createdAt: string;
	updatedAt: string;
	assignments: { products: number; taxons: number };
}

export interface CmsTemplate extends TemplateSummary {
	blocks: PageBlock[];
	grapesData: Record<string, unknown> | null;
}

export interface TemplateAssignments {
	id: number;
	type: TemplateType;
	productCodes: string[];
	taxonCodes: string[];
	products: { code: string; name: string }[];
	taxons: { code: string; name: string }[];
}

/** Current product of a product template: what the `produit-*` blocks read. */
export interface ProductContext {
	product: ProductDetail;
	breadcrumb: Breadcrumb[];
	/** Associated products (Sylius associations, else same main category), up to 8. */
	related: ProductSummary[];
}

/** Products of the category page: the URL state (?q=&tri=&par=&page=) over the block's initial sort / page size. */
export interface TaxonListing {
	result: ProductPage;
	params: ListingParams;
	/** Initial sort / page size (`categorie-produits` props), dropped from the URLs. */
	defaults: { sort: SortKey; perPage: number };
	sorts: SortOption[];
}

/** Current category of a category template: what the `categorie-*` blocks read. */
export interface TaxonContext {
	taxon: { code: string; name: string; description: string | null; url: string };
	breadcrumb: Breadcrumb[];
	children: TaxonCard[];
	/** Parent level URL (null at the top of a section). */
	up: string | null;
	listing: TaxonListing;
}
