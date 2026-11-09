<script lang="ts">
	// Résultats de /assistance : alerte si le n° de série et la référence divergent, bandeau du niveau d'ensemble,
	// n° de série (vues éclatées et garantie s'il est connu du parc), fiche produit, groupes de documents par
	// niveau (le niveau famille replié, avec son avertissement ; son titre h2 dans le <summary>, pour que ses intitulés h3
	// suivent un h2 même quand seul ce niveau répond), ou choix du modèle quand la saisie est un nom (au
	// plus 8 ; `moreChoices` : invitation à préciser). En cas de conflit, chaque groupe dit sa source : n° de série
	// ou référence saisie. Sous les groupes : « Une question sur ce modèle ? » (ManualQuestion) si un document exact
	// ou série a son texte indexé (`askable`), sinon une phrase le dit, suivie des modèles où la question répond. Niveau
	// « aucun » : le lien vers le support et les exemples de démonstration (AssistanceExamples), jamais une impasse.
	// Modèle interrogeable : chaque carte dit si son manuel répond aux questions ou s'ouvre en PDF seulement.
	// En tête des groupes, « Les plus utiles » (`featuredManuals`, au plus deux documents, règle dans assistance.ts) ;
	// ces documents ne sont pas répétés plus bas. Dans chaque groupe, les documents sont rangés par type sous un
	// intitulé (`documentsByType`), le français d'abord.
	// `{'\n'}` : une ligne de HTML par choix et par document (source lisible, contrôles ligne à ligne par grep).
	import {
		MORE_CHOICES_MESSAGE,
		QUESTION_MESSAGES,
		assistanceUrl,
		conflictMessage,
		contactUrl,
		documentsByType,
		featuredManuals,
		featuredTitle,
		indexedManuals,
		levelLabel,
		productUrl,
		questionTarget,
		reasonLabel,
		sourceLabel
	} from './assistance.js';
	import AssistanceExamples from './AssistanceExamples.svelte';
	import ManualCard from './ManualCard.svelte';
	import ManualQuestion from './ManualQuestion.svelte';
	import type { GroupSource, ManualSearch } from './types.js';

	/** `brand` : marque validée par le visiteur (`?marque=`), montrée quand l'API n'en donne pas. */
	let { search, brand = '' }: { search: ManualSearch; brand?: string } = $props();

	const reference = $derived(search.reference?.canonical ?? null);
	/** Ce que le visiteur a cherché, pour le bandeau « aucun » et la demande au support. */
	const asked = $derived(reference ?? search.serial?.value ?? search.query);
	const product = $derived(search.product ? { ...search.product, url: productUrl(search.product) } : null);
	const choosing = $derived(search.choices.length > 0 && search.groups.length === 0);
	/**
	 * Référence citée dans le bandeau du niveau. En cas de conflit, les documents viennent aussi des modèles du
	 * n° de série : le bandeau ne les attribue pas à la référence saisie (l'alerte au-dessus dit l'écart).
	 */
	const levelReference = $derived(search.serial?.conflict ? null : reference);
	/** Groupe du niveau d'ensemble : sa raison explique le bandeau (série, famille). */
	const best = $derived(search.groups.find((group) => group.level === search.level) ?? null);
	/** Conflit : les groupes des deux sources se suivent, chacun dit d'où il vient. */
	const showSource = $derived(search.serial?.conflict === true);
	/** Référence citée par la raison d'un groupe : seulement pour les documents de la référence saisie. */
	const groupReference = (group: { source: GroupSource }): string | null => (group.source === 'reference' ? reference : null);
	/** Modèle interrogé par la question (référence comprise, sinon produit fini du n° de série) et ses manuels indexés. */
	const target = $derived(search.askable ? questionTarget(search) : null);
	const indexed = $derived(target ? indexedManuals(search, target.source) : []);
	/** « Les plus utiles », puis le reste de chaque groupe, sans répéter les documents mis en avant. */
	const featured = $derived(featuredManuals(search));
	const featuredIds = $derived(new Set(featured.map((pick) => pick.document.id)));
	const groups = $derived(
		search.groups
			.map((group) => ({ ...group, sections: documentsByType(group.documents.filter((document) => !featuredIds.has(document.id))) }))
			.filter((group) => group.sections.length > 0)
	);
	/** La carte dit si son manuel répond aux questions quand le modèle interrogé est celui de son groupe. */
	const askableFor = (source: GroupSource): boolean => target !== null && source === target.source;
</script>

<div class="yack-assistance-results" data-test-assistance-results>
	{#if search.serial?.conflict}
		<div class="alert alert-warning" role="alert" data-test-assistance-conflict>{conflictMessage(search.serial, reference)}</div>
	{/if}

	{#if choosing}
		<section class="yack-assistance-choices" aria-labelledby="yack-assistance-choices-title">
			<h2 class="h5" id="yack-assistance-choices-title">Plusieurs modèles correspondent : choisissez le vôtre</h2>
			{#if search.moreChoices}
				<p class="yack-assistance-choices__more" data-test-assistance-more-choices>{MORE_CHOICES_MESSAGE}</p>
			{/if}
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
				<p class="yack-assistance-level__title">{levelLabel(search.level)}{levelReference ? ` : ${levelReference}` : ''}</p>
				{#if best && search.level !== 'exact'}<p class="yack-assistance-level__text">{reasonLabel(best.reason, levelReference)}</p>{/if}
				{#if search.reference?.brand}
					<p class="yack-assistance-level__text">Marque {search.reference.brand}{search.reference.brandSource === 'deduite' ? ' (déduite de la référence)' : ''}</p>
				{:else if brand}
					<p class="yack-assistance-level__text">Marque {brand}</p>
				{/if}
			{/if}
		</div>
		{#if search.level === 'aucun'}<AssistanceExamples title="Ou essayez une référence de démonstration" />{/if}
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

	{#if featured.length > 0}
		<section class="yack-assistance-group" aria-labelledby="yack-assistance-featured-title" data-test-assistance-featured>
			<h2 class="yack-assistance-group__title" id="yack-assistance-featured-title">{featuredTitle(featured.length)}</h2>
			<ul class="yack-assistance-group__list">
				{#each featured as pick (pick.document.id)}{'\n'}<li><ManualCard document={pick.document} askable={askableFor(pick.source)} showType note={pick.level === 'serie' ? reasonLabel(pick.reason, groupReference(pick)) : ''} /></li>{/each}
			</ul>
		</section>
	{/if}

	{#each groups as group, index (index)}
		{@const titleId = `yack-assistance-group-${index}`}
		{#if group.level === 'famille'}
			<details class="yack-assistance-group yack-assistance-group--famille" data-test-assistance-group={group.level}>
				<summary class="yack-assistance-group__title" id={titleId}>
					<h2 class="yack-assistance-group__heading">{levelLabel(group.level)} ({group.documents.length})</h2>{#if showSource}<span class="visually-hidden">{', '}</span><span class="yack-assistance-group__source yack-assistance-group__source--{group.source}" data-test-assistance-source={group.source}>{sourceLabel(group.source, search.serial, reference)}</span>{/if}
				</summary>
				<p class="yack-assistance-group__warning">{reasonLabel(group.reason, groupReference(group))}</p>
				{#each group.sections as section (section.type)}
					<h3 class="yack-assistance-group__type" data-test-assistance-type={section.type}>{section.label}</h3>
					<ul class="yack-assistance-group__list">
						{#each section.documents as document (document.id)}{'\n'}<li><ManualCard {document} headingLevel={4} /></li>{/each}
					</ul>
				{/each}
			</details>
		{:else}
			<section class="yack-assistance-group" aria-labelledby={titleId} data-test-assistance-group={group.level}>
				<h2 class="yack-assistance-group__title" id={titleId}>
					{levelLabel(group.level)}{#if showSource}<span class="visually-hidden">{', '}</span><span class="yack-assistance-group__source yack-assistance-group__source--{group.source}" data-test-assistance-source={group.source}>{sourceLabel(group.source, search.serial, reference)}</span>{/if}
				</h2>
				{#if group.level === 'serie' && (search.level !== 'serie' || showSource)}<p class="yack-assistance-group__warning">{reasonLabel(group.reason, groupReference(group))}</p>{/if}
				{#each group.sections as section (section.type)}
					<h3 class="yack-assistance-group__type" data-test-assistance-type={section.type}>{section.label}</h3>
					<ul class="yack-assistance-group__list">
						{#each section.documents as document (document.id)}{'\n'}<li><ManualCard {document} askable={askableFor(group.source)} headingLevel={4} /></li>{/each}
					</ul>
				{/each}
			</section>
		{/if}
	{/each}

	{#if target}
		<ManualQuestion reference={target.reference} {indexed} />
	{:else if search.groups.length > 0}
		<div class="yack-assistance-question-none" data-test-assistance-question-none>
			<p class="mb-0">{QUESTION_MESSAGES.noIndex}</p>
			<AssistanceExamples title={QUESTION_MESSAGES.tryIndexed} only="questions" />
		</div>
	{/if}
</div>
