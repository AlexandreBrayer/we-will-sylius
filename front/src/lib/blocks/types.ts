// Contract shared with Sylius (App\Yack\Content\Domain\BlockType / PageBlocks, docs/API_FRONT.md §11).
import type { Breadcrumb, ListingParams, ProductDetail, ProductPage, ProductSummary, SortKey, SortOption } from '#lib/types.js';
import type { ContainerType } from './containers.js';
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
	| 'accompagnement'
	| 'titre'
	| 'texte';

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

/** Content blocks: everything but the layout containers. */
export type ContentBlockType = FreeBlockType | ProductBlockType | TaxonBlockType;

/** Every node type of a page: content blocks and layout containers (section, colonnes: #lib/blocks/containers.ts). */
export type BlockType = ContentBlockType | ContainerType;

export type BlockProps = Record<string, string>;

export interface PageBlock {
	type: BlockType;
	props: BlockProps;
	/**
	 * Named style (App\Yack\Content\Domain\BlockStyle). The API sends `[]` for an empty style and
	 * no key for a block saved before styles existed: read it through readStyle() / styleToCss().
	 */
	style?: BlockStyle;
	/** Section only: its colonnes and free content blocks (normalized: always set on a section). */
	children?: PageBlock[];
	/** Colonnes only: the content blocks of each column, as many columns as its ratio (always set on a colonnes). */
	columns?: PageBlock[][];
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

/** Products of the category page: the URL state (?q=&tri=&page=) over the block's initial sort; page size of the block. */
export interface TaxonListing {
	result: ProductPage;
	params: ListingParams;
	/** Initial sort (dropped from the URLs) and page size of the `categorie-produits` props. */
	defaults: { sort: SortKey; perPage: number };
	sorts: SortOption[];
}

/** Current category of a category template: what the `categorie-*` blocks read. */
export interface TaxonContext {
	taxon: { code: string; name: string; description: string | null; url: string };
	breadcrumb: Breadcrumb[];
	children: TaxonCard[];
	/** Categories of the parent level, this one included (empty at the top of a section). */
	siblings: TaxonCard[];
	/** Parent level URL (null at the top of a section). */
	up: string | null;
	listing: TaxonListing;
}
