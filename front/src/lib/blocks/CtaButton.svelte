<script lang="ts">
	import { resolveLink } from './parse.js';

	let { label = '', url = '', variant = 'btn-primary' }: { label?: string; url?: string; variant?: string } =
		$props();
</script>

{#if label && url}
	<a class="btn btn-lg {variant}" href={resolveLink(url)}>{label}</a>
{/if}
