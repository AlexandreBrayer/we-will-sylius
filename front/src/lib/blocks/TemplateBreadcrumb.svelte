<script lang="ts">
	// Breadcrumb above the blocks of a template page (product / category): shop and builder canvas.
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import type { Breadcrumb } from '#lib/types.js';

	let { items }: { items: Breadcrumb[] } = $props();
</script>

<div class="container mt-4">
	<Breadcrumbs {items} />
</div>
