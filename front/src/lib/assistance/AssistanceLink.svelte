<script lang="ts">
	// Lien discret vers /assistance (Manuels et notices) depuis une autre page (/sav, sous la recherche). Rien quand le
	// module est coupé (YACK_ASSISTANCE=0, `assistanceEnabled`).
	import Icon from '#lib/layout/Icon.svelte';
	import { assistanceEnabled } from '#lib/remote/assistance.remote.js';
</script>

{#if await assistanceEnabled()}
	<p class="yack-assistance-link">
		<a href="/assistance" data-test-assistance-link><Icon name="document" size="sm" />Trouver le manuel d'un appareil</a>
	</p>
{/if}
