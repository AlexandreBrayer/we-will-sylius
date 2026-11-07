<script lang="ts">
	import CtaButton from './CtaButton.svelte';
	import HeroProfileCard from './HeroProfileCard.svelte';
	import { getYackContext } from './context.js';
	import { assetUrl } from './parse.js';

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
		card = 'non'
	} = $props();
	// In a product / category template the page heading is the product / category name.
	const ctx = getYackContext();
	const heading = ctx.product !== undefined || ctx.taxon !== undefined ? 'h2' : 'h1';
	// `photo`: the visual is the background (maquette gradient over it), not a right column.
	const photo = $derived(theme === 'photo' && image !== '');
	// Right column: the profile card replaces the visual.
	const side = $derived(card === 'profil' ? 'card' : image && !photo ? 'image' : null);
	const light = $derived(theme === 'clair');
</script>

<section class="yack-cms-section yack-cms-hero yack-cms-theme-{theme}" data-yack-block="hero">
	{#if photo}
		<span class="yack-cms-hero__bg"><img src={assetUrl(image)} alt="" /></span>
	{/if}
	<div class="container yack-cms-hero__in">
		<div class="row align-items-center g-5">
			<div class={side ? 'col-lg-7' : 'col-lg-9'}>
				{#if kicker}<p class="yack-cms-kicker">{kicker}</p>{/if}
				<svelte:element this={heading} class="yack-cms-hero__title">{title}</svelte:element>
				{#if subtitle}<p class="yack-cms-hero__subtitle">{subtitle}</p>{/if}
				<div class="d-flex flex-wrap gap-2">
					<CtaButton label={ctaLabel} url={ctaUrl} variant={light || photo ? 'btn-primary' : 'btn-light'} />
					<CtaButton label={cta2Label} url={cta2Url} variant={photo ? 'btn-light' : light ? 'btn-outline-primary' : 'btn-outline-light'} />
				</div>
			</div>
			{#if side === 'card'}
				<div class="col-lg-5">
					<HeroProfileCard />
				</div>
			{:else if side === 'image'}
				<div class="col-lg-5">
					<div class="yack-cms-visual"><img src={assetUrl(image)} alt="" loading="lazy" /></div>
				</div>
			{/if}
		</div>
	</div>
</section>
