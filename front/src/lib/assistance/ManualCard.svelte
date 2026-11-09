<script lang="ts">
	// Un document de /assistance, lisible d'un coup d'œil : titre court (sans la liste des modèles, `shortTitle`),
	// puis langues (français d'abord), « Officiel », année, pages et, si le modèle cherché a « Une question sur ce
	// modèle ? » (`askable`), une seule indication : « Répond aux questions » (texte indexé) ou « PDF seulement ».
	// En ligne secondaire repliée : les modèles couverts, le n° du manuel et, si le titre a été raccourci, le titre
	// complet. Lien vers le PDF officiel dans un nouvel onglet (https seulement), décrit par le titre et la ligne
	// d'informations (aria-describedby) : chaque « Ouvrir le PDF » se distingue des autres. Textes masqués en
	// expression (`{' … '}`) : Svelte retire les espaces en début et fin de balise.
	// `showType` : type au-dessus du titre, quand la carte n'est pas déjà sous l'intitulé de son type (« Les plus
	// utiles »). `note` : précision du groupe d'origine (document de la même série mis en avant). `headingLevel` : niveau
	// du titre selon la place de la carte dans la page.
	import Icon from '#lib/layout/Icon.svelte';
	import { QUESTION_MESSAGES, modelsSummary, pagesLabel, pdfPageUrl, shortTitle } from './assistance.js';
	import type { ManualDocument } from './types.js';

	let {
		document,
		askable = false,
		showType = false,
		note = '',
		headingLevel = 3
	}: { document: ManualDocument; askable?: boolean; showType?: boolean; note?: string; headingLevel?: 3 | 4 } = $props();

	/** Id propre à chaque carte : un même document peut figurer dans les groupes des deux sources. */
	const id = $props.id();
	const titleId = `${id}-title`;
	const metaId = `${id}-meta`;

	const pdf = $derived(pdfPageUrl(document.url));
	const pages = $derived(pagesLabel(document.pages));
	const title = $derived(shortTitle(document.title));
	const shortened = $derived(title !== document.title.trim());
	const summary = $derived(modelsSummary(document));
	const folded = $derived(document.models.length > 0 || shortened);
</script>

<article class="yack-assistance-card" data-test-assistance-document={document.id}>
	{#if showType}<p class="yack-assistance-card__type">{document.typeLabel}</p>{/if}
	<svelte:element this={`h${headingLevel}`} class="yack-assistance-card__title" id={titleId}>{title}</svelte:element>

	<ul class="yack-assistance-card__meta" id={metaId}>
		{#if document.languages.length > 0}
			<li><span class="visually-hidden">{'Langues : '}</span>{document.languages.map((code) => code.toUpperCase()).join(' · ')}</li>
		{/if}
		{#if document.official}<li class="yack-assistance-card__official" data-test-assistance-official>Officiel</li>{/if}
		{#if document.year}<li>{document.year}</li>{/if}
		{#if pages}<li>{pages}</li>{/if}
		{#if askable}
			{#if document.hasText}
				<li data-test-assistance-indexed-document>{QUESTION_MESSAGES.indexed}</li>
			{:else}
				<li data-test-assistance-pdf-only>{QUESTION_MESSAGES.pdfOnly}</li>
			{/if}
		{/if}
	</ul>

	{#if note}<p class="yack-assistance-card__note" data-test-assistance-featured-note>{note}</p>{/if}

	{#if folded}
		<details class="yack-assistance-card__more" data-test-assistance-models>
			<summary>{summary || 'Titre complet'}</summary>
			{#if document.models.length > 0}<p>Modèles : {document.models.join(', ')}</p>{/if}
			{#if shortened}<p>Titre complet : {document.title}</p>{/if}
		</details>
	{:else if summary}
		<p class="yack-assistance-card__more" data-test-assistance-models>{summary}</p>
	{/if}

	{#if pdf}
		<a class="btn btn-outline-primary yack-assistance-card__open" href={pdf} target="_blank" rel="noopener" aria-describedby="{titleId} {metaId}" data-test-assistance-pdf>
			<Icon name="document" />Ouvrir le PDF<span class="visually-hidden">{' (nouvel onglet)'}</span>
		</a>
	{/if}
</article>
