<script lang="ts">
	import CtaButton from './CtaButton.svelte';
	import { getYackContext } from './context.js';
	import { assetUrl } from './parse.js';

	let { title = '', subtitle = '', image = '', theme = 'magenta', ctaLabel = '', ctaUrl = '' } = $props();
	const ctx = getYackContext();
</script>

<section class="yack-cms-section yack-cms-hero yack-cms-theme-{theme}" data-yack-block="hero">
	<div class="container py-6">
		<div class="row align-items-center g-5">
			<div class={image ? 'col-lg-7' : 'col-lg-9'}>
				<h1 class="display-5 fw-bold">{title}</h1>
				{#if subtitle}<p class="lead mb-4">{subtitle}</p>{/if}
				<CtaButton label={ctaLabel} url={ctaUrl} variant={theme === 'clair' ? 'btn-primary' : 'btn-light'} />
			</div>
			{#if image}
				<div class="col-lg-5">
					<div class="yack-cms-visual"><img src={assetUrl(image, ctx.syliusUrl)} alt="" loading="lazy" /></div>
				</div>
			{/if}
		</div>
	</div>
</section>
