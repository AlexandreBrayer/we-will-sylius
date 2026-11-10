<script lang="ts">
	import CtaButton from './CtaButton.svelte';
	import { textEditor } from './editable.js';
	import { imageView } from './image.js';
	import { assetUrl } from './parse.js';
	import { rowAlignment, titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let { title = '', text = '', image = '', imagePosition = 'gauche', ctaLabel = '', ctaUrl = '', imageAlt = '', imageFit = '', imageFocus = '' } = $props();
	// Alternative text, framing and focal point of the visual (image.ts).
	const view = $derived(imageView({ imageAlt, imageFit, imageFocus }, 'image'));
	const edit = textEditor();
	// Tag of the main title (style `balise`, h2 by default) and its look: styleContext.ts, style.ts titleTag().
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
	// Vertical alignment of the two columns (style `alignementVertical`, centred by default).
	const rowAlign = $derived(rowAlignment(style()));
</script>

<section class="yack-cms-section container" data-yack-block="texte-image">
	<div class="row {rowAlign} g-5">
		<div class="col-lg-6 {imagePosition === 'droite' ? 'order-lg-2' : ''}">
			<div class="yack-cms-visual yack-cms-visual--light">
				{#if image}<img src={assetUrl(image)} alt={view.alt} loading="lazy" class={view.class} />{/if}
			</div>
		</div>
		<div class="col-lg-6">
			<svelte:element this={heading.tag} class={['mb-3', heading.look]} {@attach edit('title')}>{title}</svelte:element>
			<p class="yack-cms-text mb-4" {@attach edit('text')}>{text}</p>
			<CtaButton label={ctaLabel} url={ctaUrl} edit={edit('ctaLabel')} />
		</div>
	</div>
</section>
