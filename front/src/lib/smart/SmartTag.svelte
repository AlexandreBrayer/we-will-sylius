<script lang="ts" module>
	// Pictogramme « puce » : symbole « memory » de Material Symbols Rounded (Google, licence Apache 2.0), même famille
	// que les pictos de la maquette de Quentin (pleins, une couleur), optimisé par svgo. Seul pictogramme des
	// fonctions intelligentes : dans la pastille, jamais sur les boutons.
	const CHIP =
		'M360-400v-160q0-17 11.5-28.5T400-600h160q17 0 28.5 11.5T600-560v160q0 17-11.5 28.5T560-360H400q-17 0-28.5-11.5T360-400m80-40h80v-80h-80zm-80 280v-40h-80q-33 0-56.5-23.5T200-280v-80h-40q-17 0-28.5-11.5T120-400t11.5-28.5T160-440h40v-80h-40q-17 0-28.5-11.5T120-560t11.5-28.5T160-600h40v-80q0-33 23.5-56.5T280-760h80v-40q0-17 11.5-28.5T400-840t28.5 11.5T440-800v40h80v-40q0-17 11.5-28.5T560-840t28.5 11.5T600-800v40h80q33 0 56.5 23.5T760-680v80h40q17 0 28.5 11.5T840-560t-11.5 28.5T800-520h-40v80h40q17 0 28.5 11.5T840-400t-11.5 28.5T800-360h-40v80q0 33-23.5 56.5T680-200h-80v40q0 17-11.5 28.5T560-120t-28.5-11.5T520-160v-40h-80v40q0 17-11.5 28.5T400-120t-28.5-11.5T360-160m320-120v-400H280v400z';
</script>

<script lang="ts">
	// Repère des fonctions intelligentes (ADR 0003) : pastille turquoise de la charte, petit pictogramme décoratif
	// (le libellé dit tout), couleurs dans _tokens.scss ($yack-smart-*).
	// `simulated` : résultat produit par l'adapter de démonstration (YACK_AI_ADAPTER=fake), dit tel quel.
	let { label = 'Fonction intelligente', simulated = false }: { label?: string; simulated?: boolean } = $props();
</script>

<span class="yack-smart-tag" data-test-smart-tag>
	<svg class="yack-smart-tag__icon" viewBox="0 -960 960 960" aria-hidden="true" focusable="false"><path fill="currentColor" d={CHIP} /></svg>
	<span>{label}{#if simulated}<span class="visually-hidden">, </span> · démonstration{/if}</span>
</span>
