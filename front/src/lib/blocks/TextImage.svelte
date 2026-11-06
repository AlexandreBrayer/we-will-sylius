<script lang="ts">
	import CtaButton from './CtaButton.svelte';
	import { assetUrl } from './parse.js';

	let { title = '', text = '', image = '', imagePosition = 'gauche', ctaLabel = '', ctaUrl = '' } = $props();
</script>

<section class="yack-cms-section container" data-yack-block="texte-image">
	<div class="row align-items-center g-5">
		<div class="col-lg-6 {imagePosition === 'droite' ? 'order-lg-2' : ''}">
			<div class="yack-cms-visual yack-cms-visual--light">
				{#if image}<img src={assetUrl(image)} alt="" loading="lazy" />{/if}
			</div>
		</div>
		<div class="col-lg-6">
			<h2 class="mb-3">{title}</h2>
			<p class="yack-cms-text mb-4">{text}</p>
			<CtaButton label={ctaLabel} url={ctaUrl} />
		</div>
	</div>
</section>
