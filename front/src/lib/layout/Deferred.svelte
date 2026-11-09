<script lang="ts">
	// Secondary async content (await inside `children`) that must not hold the page:
	//  - SSR: no pending state, the content is awaited and rendered (full HTML without JS);
	//  - browser: when created by a client-side navigation, shows `pending` (skeleton) until the
	//    awaits inside settle, instead of delaying the whole page.
	// Main content of a page awaits without boundary (the navigation completes once it is ready).
	import { browser } from '$app/env';
	import type { Snippet } from 'svelte';

	let { children, pending }: { children: Snippet; pending: Snippet } = $props();
</script>

<svelte:boundary pending={browser ? pending : undefined}>
	{@render children()}
</svelte:boundary>
