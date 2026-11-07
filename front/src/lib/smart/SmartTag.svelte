<script lang="ts">
	// Repère des fonctions intelligentes (lot 2, ADR 0003) : couleur d'accent $yack-smart, distincte de la charte.
	// `simulated` : résultat produit par l'adapter de démonstration (YACK_AI_ADAPTER=fake), dit tel quel.
	import Icon from '#lib/layout/Icon.svelte';

	let { label = 'Fonction intelligente', simulated = false }: { label?: string; simulated?: boolean } = $props();
</script>

<span class="yack-smart-tag" data-test-smart-tag>
	<Icon name="sparkles" size="sm" />{label}{#if simulated}<span class="visually-hidden">, </span> · démonstration{/if}
</span>
