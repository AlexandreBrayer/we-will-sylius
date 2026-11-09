<script lang="ts">
	// /fonctions-intelligentes : index des fonctions intelligentes (décision d'Aurélien du 07/11/2026). Pas de
	// démonstration ici : chaque carte renvoie vers la fonction là où elle sert dans le site.
	import { assistanceEnabled } from '#lib/remote/assistance.remote.js';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import { SMART_FUNCTIONS, STATUS_LABELS, type SmartFunction } from '#lib/smart/functions.js';

	// Module « Manuels et notices » coupé (YACK_ASSISTANCE=0) : sa carte reste, sans lien (« en cours de branchement »).
	const assistance = $derived(await assistanceEnabled());
	const urlOf = (fn: SmartFunction) => (fn.url === '/assistance' && !assistance ? null : fn.url);
</script>

<svelte:head>
	<title>Fonctions intelligentes | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container my-5">
	<SmartTag />
	<h1 class="mt-2 mb-2">Fonctions intelligentes</h1>
	<p class="lead mb-4">
		Elles viennent en plus des fonctions B2B, jamais à leur place, et apparaissent là où elles servent. Cette page les recense.
	</p>

	<ul class="yack-smart-index list-unstyled" data-test-smart-index>
		{#each SMART_FUNCTIONS as fn (fn.key)}
			{@const url = urlOf(fn)}
			<li>
				<article class="yack-smart-index__card" data-test-smart-function={fn.key}>
					<span class="yack-smart-index__status" class:yack-smart-index__status--new={fn.status === 'nouveaute'}>{STATUS_LABELS[fn.status]}</span>
					<h2 class="h5 mb-0">{fn.title}</h2>
					<p class="mb-0">{fn.why}</p>
					<p class="small text-body-secondary mb-0">Pour : {fn.audience}</p>
					{#if url}
						<a class="yack-smart-index__where" href={url}>Voir dans {fn.where}</a>
					{:else}
						<span class="yack-smart-index__where">Dans : {fn.where} (en cours de branchement)</span>
					{/if}
				</article>
			</li>
		{/each}
	</ul>
</div>
