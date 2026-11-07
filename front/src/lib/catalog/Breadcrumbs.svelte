<script lang="ts">
	// Grey breadcrumb of the Yack charter (_catalog.scss, .yack-breadcrumb): catalog, product, SAV, account.
	import type { Breadcrumb } from '#lib/types.js';

	let { items }: { items: Breadcrumb[] } = $props();
</script>

<nav aria-label="Fil d'Ariane">
	<ol class="breadcrumb yack-breadcrumb">
		{#each items as item, i (i)}
			{@const last = i === items.length - 1}
			<li class="breadcrumb-item" class:active={last} aria-current={last ? 'page' : undefined}>
				{#if item.url}
					<a href={item.url}>{item.label}</a>
				{:else}
					<span class="text-break">{item.label}</span>
				{/if}
			</li>
		{/each}
	</ol>
</nav>
