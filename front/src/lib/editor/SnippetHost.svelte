<script lang="ts">
	// Renders a snippet of the editor page inside the canvas iframe (shop header / footer around the
	// template, see CanvasChrome in grapesSetup.ts): the snippet keeps the reactivity of its page.
	import type { Snippet } from 'svelte';

	let { snippet }: { snippet: Snippet } = $props();
</script>

{@render snippet()}
