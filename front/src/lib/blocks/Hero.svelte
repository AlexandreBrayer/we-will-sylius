<script lang="ts">
	import CtaButton from './CtaButton.svelte';
	import HeroProfileCard from './HeroProfileCard.svelte';
	import { getYackContext } from './context.js';
	import { textEditor } from './editable.js';
	import { imageView } from './image.js';
	import { assetUrl } from './parse.js';
	import { rowAlignment, titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let {
		kicker = '',
		title = '',
		subtitle = '',
		image = '',
		theme = 'magenta',
		ctaLabel = '',
		ctaUrl = '',
		cta2Label = '',
		cta2Url = '',
		card = 'non',
		imageAlt = '',
		imageFit = '',
		imageFocus = ''
	} = $props();
	// In a product / category template the page heading is the product / category name: h2 by default, h1 otherwise.
	// The style `balise` chooses another tag, with the look of the default one (style.ts titleTag()).
	const ctx = getYackContext();
	const edit = textEditor();
	const style = blockStyle();
	const heading = $derived(titleTag(style(), ctx.product !== undefined || ctx.taxon !== undefined ? 'h2' : 'h1'));
	// Vertical alignment of the text and its visual or card (style `alignementVertical`, centred by default).
	const rowAlign = $derived(rowAlignment(style()));
	// `photo`: the visual is the background (maquette gradient over it), not a right column.
	const photo = $derived(theme === 'photo' && image !== '');
	// Right column: the profile card replaces the visual.
	const side = $derived(card === 'profil' ? 'card' : image && !photo ? 'image' : null);
	const light = $derived(theme === 'clair');
	// Alternative text, framing and focal point of the visual (image.ts).
	const view = $derived(imageView({ imageAlt, imageFit, imageFocus }, 'image'));
</script>

<section class="yack-cms-section yack-cms-hero yack-cms-theme-{theme}" data-yack-block="hero">
	{#if photo}
		<span class="yack-cms-hero__bg"><img src={assetUrl(image)} alt={view.alt} class={view.class} /></span>
	{/if}
	<div class="container yack-cms-hero__in">
		<div class="row {rowAlign} g-5">
			<div class={side ? 'col-lg-7' : 'col-lg-9'}>
				{#if kicker}<p class="yack-cms-kicker" {@attach edit('kicker')}>{kicker}</p>{/if}
				<svelte:element this={heading.tag} class={['yack-cms-hero__title', heading.look]} {@attach edit('title')}>{title}</svelte:element>
				{#if subtitle}<p class="yack-cms-hero__subtitle" {@attach edit('subtitle')}>{subtitle}</p>{/if}
				<div class="d-flex flex-wrap gap-2">
					<CtaButton label={ctaLabel} url={ctaUrl} variant={light || photo ? 'btn-primary' : 'btn-light'} edit={edit('ctaLabel')} />
					<CtaButton label={cta2Label} url={cta2Url} variant={photo ? 'btn-light' : light ? 'btn-outline-primary' : 'btn-outline-light'} edit={edit('cta2Label')} />
				</div>
			</div>
			{#if side === 'card'}
				<div class="col-lg-5">
					<HeroProfileCard />
				</div>
			{:else if side === 'image'}
				<div class="col-lg-5">
					<div class="yack-cms-visual"><img src={assetUrl(image)} alt={view.alt} loading="lazy" class={view.class} /></div>
				</div>
			{/if}
		</div>
	</div>
</section>
