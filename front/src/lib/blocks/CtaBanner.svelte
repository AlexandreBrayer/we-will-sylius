<script lang="ts">
	import CtaButton from './CtaButton.svelte';
	import { textEditor } from './editable.js';
	import { titleTag } from './style.js';
	import { blockStyle } from './styleContext.js';

	let { kicker = '', title = '', text = '', theme = 'magenta', ctaLabel = '', ctaUrl = '' } = $props();
	// Light background (jaune): magenta button; the others (turquoise = maquette F-Gas gradient): light button.
	const variant = $derived(theme === 'jaune' ? 'btn-primary' : 'btn-light');
	const edit = textEditor();
	// Tag of the main title (style `balise`, h2 by default) and its look: styleContext.ts, style.ts titleTag().
	const style = blockStyle();
	const heading = $derived(titleTag(style()));
</script>

<section class="yack-cms-section container" data-yack-block="cta">
	<div class="yack-cms-cta yack-cms-theme-{theme}">
		<div>
			{#if kicker}<p class="yack-cms-kicker" {@attach edit('kicker')}>{kicker}</p>{/if}
			<svelte:element this={heading.tag} class={['yack-cms-cta__title', heading.look]} {@attach edit('title')}>{title}</svelte:element>
			{#if text}<p class="yack-cms-cta__text" {@attach edit('text')}>{text}</p>{/if}
		</div>
		<CtaButton label={ctaLabel} url={ctaUrl} {variant} edit={edit('ctaLabel')} />
	</div>
</section>
