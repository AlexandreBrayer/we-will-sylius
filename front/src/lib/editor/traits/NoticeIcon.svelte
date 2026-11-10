<!--
	Mark of a notice of the panel: the text carries the information, the mark makes it visible without relying
	on a colour. `warning`: « Hors charte » banner (Lucide triangle-alert); otherwise a neutral note (Lucide info:
	hors charte au niveau Charte, lien introuvable, élément non affiché). Icons of the closed list (#lib/editor/icons.ts).
-->
<script lang="ts">
	import { Info, TriangleAlert } from '#lib/editor/icons.js';

	let { kind = 'info' }: { kind?: 'info' | 'warning' } = $props();
</script>

{#if kind === 'warning'}<TriangleAlert class="ui-ic ui-ic-sm" />{:else}<Info class="ui-ic ui-ic-sm" />{/if}
