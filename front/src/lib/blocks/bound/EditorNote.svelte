<script lang="ts">
	// Builder canvas only: why a bound block shows nothing (no sample yet, no data for the sample).
	import { getYackContext } from '../context.js';

	let { label, text }: { label: string; text: string } = $props();
	const ctx = getYackContext();
</script>

{#if ctx.editor}
	<div class="border border-2 rounded-3 p-3 my-3 text-body-secondary small" style="border-style: dashed !important;">
		<div class="fw-bold text-body">{label}</div>
		{text}
	</div>
{/if}
