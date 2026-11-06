<script lang="ts">
	import { COMPONENTS } from './components.js';
	import type { PageBlock } from './types.js';

	let { block }: { block: PageBlock } = $props();
	const Resolved = $derived(COMPONENTS[block.type] ?? null);
</script>

{#if Resolved}
	<Resolved {...block.props} />
{/if}
