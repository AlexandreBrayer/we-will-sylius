<script lang="ts">
	import { resolveLink } from './parse.js';

	let { label = '', url = '', variant = 'btn-primary' }: { label?: string; url?: string; variant?: string } =
		$props();
	// A refused link ("//host", "javascript:"…) gives no button rather than a dead one.
	const href = $derived(resolveLink(url));
</script>

{#if label && href}
	<a class="btn btn-lg {variant}" {href}>{label}</a>
{/if}
