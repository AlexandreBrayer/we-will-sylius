<script lang="ts">
	import { getYackContext } from './context.js';
	import { resolveLink } from './parse.js';

	let { label = '', url = '', variant = 'btn-primary' }: { label?: string; url?: string; variant?: string } =
		$props();
	const ctx = getYackContext();
</script>

{#if label && url}
	<a class="btn btn-lg {variant}" href={resolveLink(url, ctx.syliusUrl)}>{label}</a>
{/if}
