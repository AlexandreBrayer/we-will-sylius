<script lang="ts">
	import CtaButton from './CtaButton.svelte';

	let { title = '', text = '', theme = 'magenta', ctaLabel = '', ctaUrl = '' } = $props();
</script>

<section class="yack-cms-section container" data-yack-block="cta">
	<div class="yack-cms-cta yack-cms-theme-{theme}">
		<div>
			<h2 class="h3 mb-1">{title}</h2>
			{#if text}<p class="mb-0">{text}</p>{/if}
		</div>
		<CtaButton label={ctaLabel} url={ctaUrl} variant="btn-light" />
	</div>
</section>
