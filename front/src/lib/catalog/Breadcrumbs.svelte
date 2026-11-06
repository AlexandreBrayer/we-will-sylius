<script lang="ts">
	import type { Breadcrumb } from '#lib/types.js';

	let { items }: { items: Breadcrumb[] } = $props();
</script>

<ol class="breadcrumb" aria-label="Fil d'Ariane">
	{#each items as item, i (i)}
		<li class="breadcrumb-item fw-normal" class:active={i === items.length - 1}>
			{#if item.url}
				<a class="link-reset" href={item.url}>{item.label}</a>
			{:else}
				<span class="text-body-tertiary text-break">{item.label}</span>
			{/if}
		</li>
	{/each}
</ol>
