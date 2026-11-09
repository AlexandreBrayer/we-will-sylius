<script lang="ts">
	// Repère des fonctions intelligentes (ADR 0003) : une pastille de texte neutre, dans la charte, sans icône.
	// `simulated` : résultat produit par l'adapter de démonstration (YACK_AI_ADAPTER=fake), dit tel quel.
	let { label = 'Fonction intelligente', simulated = false }: { label?: string; simulated?: boolean } = $props();
</script>

<span class="yack-smart-tag" data-test-smart-tag>
	{label}{#if simulated}<span class="visually-hidden">, </span> · démonstration{/if}
</span>
