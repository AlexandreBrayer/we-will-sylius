<script lang="ts">
	import CtaButton from './CtaButton.svelte';

	let { kicker = '', title = '', text = '', theme = 'magenta', ctaLabel = '', ctaUrl = '' } = $props();
	// Light backgrounds (turquoise, jaune): magenta button; dark ones: light button.
	const variant = $derived(theme === 'turquoise' || theme === 'jaune' ? 'btn-primary' : 'btn-light');
</script>

<section class="yack-cms-section container" data-yack-block="cta">
	<div class="yack-cms-cta yack-cms-theme-{theme}">
		<div>
			{#if kicker}<p class="yack-cms-kicker">{kicker}</p>{/if}
			<h2 class="h3 mb-1">{title}</h2>
			{#if text}<p class="mb-0">{text}</p>{/if}
		</div>
		<CtaButton label={ctaLabel} url={ctaUrl} {variant} />
	</div>
</section>
