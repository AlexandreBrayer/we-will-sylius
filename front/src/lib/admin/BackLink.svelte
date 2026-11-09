<script lang="ts">
	// "Retour …" button of the builder headers (lists and editor): outline button of the charter, arrow as an icon.
	import type { HTMLAnchorAttributes } from 'svelte/elements';

	let { href, label, ...rest }: { href: string; label: string } & HTMLAnchorAttributes = $props();
</script>

<a {href} class="yb-btn yb-btn-outline" {...rest}
	><svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 12H5M11 18l-6-6l6-6" /></svg>{label}</a
>

<style>
	/* Flèche en icône, trait de la couleur du texte. */
	.icon {
		width: 1.125rem;
		height: 1.125rem;
		flex: none;
		fill: none;
		stroke: currentColor;
		stroke-width: 2;
		stroke-linecap: round;
		stroke-linejoin: round;
	}
</style>
