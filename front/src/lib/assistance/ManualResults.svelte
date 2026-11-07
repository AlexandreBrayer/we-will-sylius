<script lang="ts">
	// Résultats de /assistance : alerte si le n° de série et la référence divergent, bandeau du niveau d'ensemble,
	// n° de série (vues éclatées et garantie s'il est connu du parc), fiche produit, groupes de documents par
	// niveau (le niveau famille replié, avec son avertissement), ou choix du modèle quand la saisie est un nom.
	// `{'\n'}` : une ligne de HTML par choix et par document (source lisible, contrôles ligne à ligne par grep).
	import { assistanceUrl, conflictMessage, contactUrl, levelLabel, productUrl, reasonLabel } from './assistance.js';
	import ManualCard from './ManualCard.svelte';
	import type { ManualSearch } from './types.js';

	/** `brand` : marque validée par le visiteur (`?marque=`), montrée quand l'API n'en donne pas. */
	let { search, brand = '' }: { search: ManualSearch; brand?: string } = $props();

	const reference = $derived(search.reference?.canonical ?? null);
	/** Ce que le visiteur a cherché, pour le bandeau « aucun » et la demande au support. */
	const asked = $derived(reference ?? search.serial?.value ?? search.query);
	const product = $derived(search.product ? { ...search.product, url: productUrl(search.product) } : null);
	const choosing = $derived(search.choices.length > 0 && search.groups.length === 0);
	/** Groupe du niveau d'ensemble : sa raison explique le bandeau (série, famille). */
	const best = $derived(search.groups.find((group) => group.level === search.level) ?? null);
</script>

<div class="yack-assistance-results" data-test-assistance-results>
	{#if search.serial?.conflict}
		<div class="alert alert-warning" role="alert" data-test-assistance-conflict>{conflictMessage(search.serial, reference)}</div>
	{/if}

	{#if choosing}
		<section class="yack-assistance-choices" aria-labelledby="yack-assistance-choices-title">
			<h2 class="h5" id="yack-assistance-choices-title">Plusieurs modèles correspondent : choisissez le vôtre</h2>
			<ul class="yack-assistance-choices__list">
				{#each search.choices as choice (choice.code)}
					{'\n'}<li>
						<a class="yack-assistance-choices__link" href={assistanceUrl({ ref: choice.code })} data-test-assistance-choice={choice.code}>
							<span class="yack-assistance-choices__name">{choice.name}</span>
							<span class="yack-assistance-choices__code">{choice.code}</span>
						</a>
					</li>
				{/each}
			</ul>
		</section>
	{:else}
		<div class="yack-assistance-level yack-assistance-level--{search.level}" role="status" data-test-assistance-level={search.level}>
			{#if search.level === 'aucun'}
				<p class="yack-assistance-level__title">{levelLabel('aucun')}{asked ? ` pour ${asked}` : ''}</p>
				<p class="yack-assistance-level__text">
					Vérifiez la référence sur la plaque signalétique (des lettres et des chiffres, souvent après «&nbsp;Model&nbsp;» ou «&nbsp;Type&nbsp;»), ou demandez le document au support.
				</p>
				<a class="yack-assistance-level__link" href={contactUrl(asked)} data-test-assistance-contact>Contacter le support Clik&amp;Yack</a>
			{:else}
				<p class="yack-assistance-level__title">{levelLabel(search.level)}{reference ? ` : ${reference}` : ''}</p>
				{#if best && search.level !== 'exact'}<p class="yack-assistance-level__text">{reasonLabel(best.reason, reference)}</p>{/if}
				{#if search.reference?.brand}
					<p class="yack-assistance-level__text">Marque {search.reference.brand}{search.reference.brandSource === 'deduite' ? ' (déduite de la référence)' : ''}</p>
				{:else if brand}
					<p class="yack-assistance-level__text">Marque {brand}</p>
				{/if}
			{/if}
		</div>
	{/if}

	{#if search.serial || product}
		<ul class="yack-assistance-links">
			{#if search.serial}
				<li data-test-assistance-serial={search.serial.known ? 'connu' : 'inconnu'}>
					N° de série <span class="font-monospace">{search.serial.value}</span>{search.serial.known ? '' : ' : absent du parc installé'}
					{#if search.serial.savUrl}<a href={search.serial.savUrl} data-test-assistance-sav>Vues éclatées et garantie</a>{/if}
				</li>
			{/if}
			{#if product?.url}
				<li><a href={product.url} data-test-assistance-product>Voir la fiche produit {product.code}</a></li>
			{/if}
		</ul>
	{/if}

	{#each search.groups as group, index (index)}
		{@const titleId = `yack-assistance-group-${index}`}
		{#if group.level === 'famille'}
			<details class="yack-assistance-group yack-assistance-group--famille" data-test-assistance-group={group.level}>
				<summary class="yack-assistance-group__title" id={titleId}>{levelLabel(group.level)} ({group.documents.length})</summary>
				<p class="yack-assistance-group__warning">{reasonLabel(group.reason, reference)}</p>
				<ul class="yack-assistance-group__list">
					{#each group.documents as document (document.id)}{'\n'}<li><ManualCard {document} /></li>{/each}
				</ul>
			</details>
		{:else}
			<section class="yack-assistance-group" aria-labelledby={titleId} data-test-assistance-group={group.level}>
				<h2 class="yack-assistance-group__title" id={titleId}>{levelLabel(group.level)}</h2>
				{#if group.level === 'serie' && search.level !== 'serie'}<p class="yack-assistance-group__warning">{reasonLabel(group.reason, reference)}</p>{/if}
				<ul class="yack-assistance-group__list">
					{#each group.documents as document (document.id)}{'\n'}<li><ManualCard {document} /></li>{/each}
				</ul>
			</section>
		{/if}
	{/each}
</div>
