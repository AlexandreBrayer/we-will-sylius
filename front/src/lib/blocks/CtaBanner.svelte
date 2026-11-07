<script lang="ts">
	import CtaButton from './CtaButton.svelte';

	let { kicker = '', title = '', text = '', theme = 'magenta', ctaLabel = '', ctaUrl = '' } = $props();
	// Light background (jaune): magenta button; the others (turquoise = maquette F-Gas gradient): light button.
	const variant = $derived(theme === 'jaune' ? 'btn-primary' : 'btn-light');
</script>

<section class="yack-cms-section container" data-yack-block="cta">
	<div class="yack-cms-cta yack-cms-theme-{theme}">
		<div>
			{#if kicker}<p class="yack-cms-kicker">{kicker}</p>{/if}
			<h2 class="yack-cms-cta__title">{title}</h2>
			{#if text}<p class="yack-cms-cta__text">{text}</p>{/if}
		</div>
		<CtaButton label={ctaLabel} url={ctaUrl} {variant} />
	</div>
</section>
