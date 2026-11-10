import type { Component } from 'svelte';
import type { ContainerType } from './containers.js';
import type { BlockProps, ContentBlockType, PageBlock } from './types.js';
import Hero from './Hero.svelte';
import TextImage from './TextImage.svelte';
import FeaturedProducts from './FeaturedProducts.svelte';
import Categories from './Categories.svelte';
import CtaBanner from './CtaBanner.svelte';
import Faq from './Faq.svelte';
import Reassurance from './Reassurance.svelte';
import ProductGallery from './bound/ProductGallery.svelte';
import ProductInfo from './bound/ProductInfo.svelte';
import ProductBuyBox from './bound/ProductBuyBox.svelte';
import ProductAttributes from './bound/ProductAttributes.svelte';
import ProductDescription from './bound/ProductDescription.svelte';
import ProductDocuments from './bound/ProductDocuments.svelte';
import ProductSavLink from './bound/ProductSavLink.svelte';
import RelatedProducts from './bound/RelatedProducts.svelte';
import TaxonHeader from './bound/TaxonHeader.svelte';
import TaxonProductGrid from './bound/TaxonProductGrid.svelte';
import TaxonChildren from './bound/TaxonChildren.svelte';
import KeyFigures from './KeyFigures.svelte';
import QuickAccess from './QuickAccess.svelte';
import Documents from './Documents.svelte';
import Tiles from './Tiles.svelte';
import Support from './Support.svelte';
import Titre from './Titre.svelte';
import Texte from './Texte.svelte';
import Bouton from './Bouton.svelte';
import Section from './Section.svelte';
import Columns from './Columns.svelte';

/** No-prop component (bound blocks without traits) used where any block props may be passed. */
const propless = (component: Component<Record<string, never>>) => component as unknown as Component<BlockProps>;

/** Svelte component of each content block; bound blocks (bound/) read the product / category of the context. */
export const COMPONENTS: Record<ContentBlockType, Component<BlockProps>> = {
	hero: Hero,
	'texte-image': TextImage,
	produits: FeaturedProducts,
	categories: Categories,
	cta: CtaBanner,
	faq: Faq,
	reassurance: Reassurance,
	'chiffres-cles': KeyFigures,
	'acces-rapides': QuickAccess,
	documents: Documents,
	accompagnement: Support,
	tuiles: Tiles,
	titre: Titre,
	texte: Texte,
	bouton: Bouton,
	'produit-galerie': propless(ProductGallery),
	'produit-infos': propless(ProductInfo),
	'produit-achat': propless(ProductBuyBox),
	'produit-caracteristiques': ProductAttributes,
	'produit-description': ProductDescription,
	'produit-documents': ProductDocuments,
	'produit-sav': ProductSavLink,
	'produits-associes': RelatedProducts,
	'categorie-entete': TaxonHeader,
	'categorie-produits': TaxonProductGrid,
	'categorie-sous-categories': TaxonChildren
};

/** Layout containers: they render their content (children, columns) through BlockRenderer. */
export const CONTAINERS: Record<ContainerType, Component<{ block: PageBlock }>> = {
	section: Section,
	colonnes: Columns
};
