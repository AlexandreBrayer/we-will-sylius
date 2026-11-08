<script lang="ts">
	// « Une question sur ce modèle ? », sous les résultats de /assistance quand un document exact ou série a son texte
	// indexé (`askable`). Comptes connectés seulement, comme l'API le décide : un visiteur est invité à se connecter et
	// revient sur la même URL. Réponse en texte (jamais en HTML), annoncée par la zone `aria-live` (présente avant la
	// réponse pour être lue) ; chaque source mène au PDF officiel à sa page, dans un nouvel onglet. Formulaire distant
	// `askManuals` : marche sans JavaScript (POST natif, page rendue par le serveur avec la réponse).
	import { page } from '$app/state';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import { askManuals } from '#lib/remote/assistance.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';
	import { QUESTION_MAX, QUESTION_MESSAGES, QUESTION_MIN, answerSourceLabel, indexedPagesLabel, questionLoginUrl } from './assistance.js';
	import type { IndexedManual } from './types.js';

	/** `reference` : modèle interrogé (forme canonique) ; `indexed` : ses manuels à texte indexé et leurs pages. */
	let { reference, indexed = [] }: { reference: string; indexed?: IndexedManual[] } = $props();

	const viewer = $derived(await getViewer());
	const ask = $derived(askManuals.for(reference));
	const result = $derived(ask.result);
	const pending = $derived(ask.pending > 0);
	const issue = $derived(ask.fields.allIssues()?.[0]?.message);
	const loginUrl = $derived(questionLoginUrl(page.url.pathname, page.url.search));
	const pages = $derived(indexed.map((manual) => ({ ...manual, label: indexedPagesLabel(manual.pages) })));

	// Le bouton reste actif pendant la recherche (`aria-disabled`) : un bouton désactivé perd le focus, renvoyé au
	// début de la page. Une seconde demande pendant la première est ignorée.
	let sending = false;
	const enhance = $derived(
		ask.enhance(async ({ submit }) => {
			if (sending) return;
			sending = true;
			try {
				await submit();
			} finally {
				sending = false;
			}
		})
	);
</script>

<section class="yack-smart-panel yack-smart-doc yack-assistance-question" aria-labelledby="yack-assistance-question-title" data-test-assistance-question>
	<div class="yack-smart-panel__head">
		<h2 class="yack-smart-panel__title" id="yack-assistance-question-title">Une question sur ce modèle ?</h2>
		<SmartTag simulated={result?.simulated ?? false} />
	</div>
	<p class="yack-smart-panel__why">
		Réponse tirée des manuels indexés de <span class="font-monospace">{reference}</span>, avec la page source.
	</p>

	{#if !viewer.authenticated}
		<p class="yack-assistance-question__login" data-test-assistance-question-login>
			Réservé aux comptes connectés.
			<a class="yack-smart-doc__link" href={loginUrl}>Connectez-vous pour poser une question</a>
		</p>
	{:else}
		<form {...enhance} aria-busy={pending} data-test-assistance-question-form>
			<input {...ask.fields.reference.as('hidden', reference)} />
			<label class="form-label fw-bold" for="yack-assistance-question-field">Votre question</label>
			<div class="yack-smart-doc__ask yack-assistance-question__ask">
				<input
					{...ask.fields.question.as('text')}
					id="yack-assistance-question-field"
					class="form-control"
					class:is-invalid={!!issue}
					minlength={QUESTION_MIN}
					maxlength={QUESTION_MAX}
					placeholder="Code erreur, longueur de liaison, entretien…"
					autocomplete="off"
					enterkeyhint="send"
					required
					aria-describedby={issue ? 'yack-assistance-question-error' : undefined}
				/>
				<button type="submit" class="btn yack-smart-btn yack-assistance-question__submit" aria-disabled={pending} data-test-assistance-question-submit>
					{#if pending}<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Recherche…{:else}Demander{/if}
				</button>
			</div>
		</form>

		{#if issue}
			<div class="alert alert-danger mt-3 mb-0" id="yack-assistance-question-error" role="alert" data-test-assistance-question-error>{issue}</div>
		{/if}

		<div class="yack-assistance-question__live" aria-live="polite" data-test-assistance-question-live>
			{#if result}
				<div class="yack-smart-panel__result" data-test-assistance-answer>
					<p class="yack-smart-doc__question">Votre question : {result.question}</p>
					<p class="yack-smart-doc__answer" data-test-assistance-answer-text>{result.answer}</p>
					{#if result.sources.length > 0}
						<ul class="yack-assistance-question__sources">
							{#each result.sources as source (source.documentId + '#' + source.page)}
								<li>
									<a class="yack-assistance-question__source" href={source.url} target="_blank" rel="noopener" data-test-assistance-answer-source={source.page}>
										{answerSourceLabel(source)}<span class="visually-hidden">{' (PDF, nouvel onglet)'}</span>
									</a>
								</li>
							{/each}
						</ul>
						<p class="yack-smart-panel__note mt-2" data-test-assistance-first-page>{QUESTION_MESSAGES.firstPage}</p>
					{/if}
				</div>
			{/if}
		</div>
	{/if}

	{#if pages.length > 0}
		<div class="yack-smart-panel__note" data-test-assistance-indexed>
			<p class="mb-1">Seules certaines pages des manuels sont lues pour répondre :</p>
			<ul class="yack-assistance-question__indexed">
				{#each pages as manual (manual.id)}
					<li>{manual.label ? `Pages indexées : ${manual.label} (${manual.title})` : manual.title}</li>
				{/each}
			</ul>
		</div>
	{/if}
</section>
