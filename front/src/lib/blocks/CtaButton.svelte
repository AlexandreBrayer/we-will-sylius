<script lang="ts">
	import type { Attachment } from 'svelte/attachments';
	import { returnLinks } from './links.js';
	import { resolveLink } from './parse.js';

	// `edit`: in-place editing of the label in the builder canvas (editable.ts), nothing on the shop.
	let { label = '', url = '', variant = 'btn-primary', edit = undefined }: { label?: string; url?: string; variant?: string; edit?: Attachment<HTMLElement> } =
		$props();
	// A refused link ("//host", "javascript:"…) gives no button rather than a dead one.
	const link = returnLinks();
	const href = $derived(link(resolveLink(url)));
</script>

{#if label && href}
	<a class="btn btn-lg {variant}" {href} {@attach edit}>{label}</a>
{/if}
