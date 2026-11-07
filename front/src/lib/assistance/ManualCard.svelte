<script lang="ts">
	// Un document de /assistance : type, titre, langues (français d'abord), « Officiel », n° de manuel, année,
	// pages, modèles couverts et lien vers le PDF officiel dans un nouvel onglet (https seulement).
	import Icon from '#lib/layout/Icon.svelte';
	import { pagesLabel, pdfPageUrl } from './assistance.js';
	import type { ManualDocument } from './types.js';

	/** Modèles listés avant « et N autres ». */
	const MODELS_SHOWN = 6;

	let { document }: { document: ManualDocument } = $props();

	const pdf = $derived(pdfPageUrl(document.url));
	const pages = $derived(pagesLabel(document.pages));
	const shown = $derived(document.models.slice(0, MODELS_SHOWN));
	const more = $derived(document.models.length - shown.length);
</script>

<article class="yack-assistance-card" data-test-assistance-document={document.id}>
	<p class="yack-assistance-card__type">{document.typeLabel}</p>
	<h3 class="yack-assistance-card__title">{document.title}</h3>

	<ul class="yack-assistance-card__meta">
		{#if document.languages.length > 0}
			<li><span class="visually-hidden">Langues : </span>{document.languages.map((code) => code.toUpperCase()).join(' · ')}</li>
		{/if}
		{#if document.official}<li class="yack-assistance-card__official" data-test-assistance-official>Officiel</li>{/if}
		{#if document.manualNumber}<li>N° {document.manualNumber}</li>{/if}
		{#if document.year}<li>{document.year}</li>{/if}
		{#if pages}<li>{pages}</li>{/if}
	</ul>

	{#if shown.length > 0}
		<p class="yack-assistance-card__models">
			Modèles : {shown.join(', ')}{more > 0 ? ` et ${more} autre${more > 1 ? 's' : ''}` : ''}
		</p>
	{/if}

	{#if pdf}
		<a class="btn btn-outline-primary yack-assistance-card__open" href={pdf} target="_blank" rel="noopener" data-test-assistance-pdf>
			<Icon name="document" />Ouvrir le PDF{pages ? ` (${pages})` : ''}<span class="visually-hidden"> (nouvel onglet)</span>
		</a>
	{/if}
</article>
