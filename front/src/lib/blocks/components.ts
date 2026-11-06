import type { Component } from 'svelte';
import type { BlockProps, BlockType } from './types.js';
import Hero from './Hero.svelte';
import TextImage from './TextImage.svelte';
import FeaturedProducts from './FeaturedProducts.svelte';
import Categories from './Categories.svelte';
import CtaBanner from './CtaBanner.svelte';
import Faq from './Faq.svelte';
import Reassurance from './Reassurance.svelte';

export const COMPONENTS: Record<BlockType, Component<BlockProps>> = {
	hero: Hero,
	'texte-image': TextImage,
	produits: FeaturedProducts,
	categories: Categories,
	cta: CtaBanner,
	faq: Faq,
	reassurance: Reassurance
};
