<script lang="ts">
	// Documentation assistant (IA-06) under the product template: a question on the device, an answer
	// taken from its notices with the source page. Logged-in customers (PRO and ECOM) only, as the API
	// decides; a visitor is invited to log in. The answer is shown as text, never as HTML.
	import { page } from '$app/state';
	import SmartTag from '#lib/smart/SmartTag.svelte';
	import { askDocumentation, getDocumentationCoverage } from '#lib/remote/documentation.remote.js';
	import type { Viewer } from '#lib/types.js';
	import { QUESTION_MAX, sourceLabel } from './types.js';

	let { productCode, viewer }: { productCode: string; viewer: Viewer } = $props();

	const ask = $derived(askDocumentation.for(productCode));
	const coverage = $derived(viewer.authenticated ? await getDocumentationCoverage(productCode) : null);
	const result = $derived(ask.result);
	const issue = $derived(ask.fields.allIssues()?.[0]?.message);
	const loginUrl = $derived(`/connexion?redirect=${encodeURIComponent(page.url.pathname + page.url.search)}`);

	let formElement: HTMLFormElement | undefined = $state();

	/** Sample question: fills the field and asks it (JavaScript only; without it, the text can be typed). */
	function askExample(question: string): void {
		ask.fields.question.set(question);
		formElement?.requestSubmit();
	}
</script>

<section class="yack-smart-panel yack-smart-doc" aria-labelledby="yack-smart-doc-title" data-test-documentation-panel>
	<div class="yack-smart-panel__head">
		<SmartTag simulated={result?.simulated ?? false} />
		<h2 id="yack-smart-doc-title" class="yack-smart-panel__title">Une question sur cet appareil ?</h2>
	</div>
	<p class="yack-smart-panel__why">Réponse tirée des notices, avec la page source.</p>

	{#if !viewer.authenticated}
		<p class="mb-0" data-test-documentation-login>
			Réservé aux comptes professionnels connectés.
			<a class="yack-smart-doc__link" href={loginUrl}>Se connecter</a>
		</p>
	{:else}
		<form bind:this={formElement} {...ask.enhance(async ({ submit }) => void (await submit()))}>
			<input {...ask.fields.productCode.as('hidden', productCode)} />
			<label class="visually-hidden" for="yack-smart-doc-question">Votre question</label>
			<div class="yack-smart-doc__ask">
				<input
					{...ask.fields.question.as('text')}
					id="yack-smart-doc-question"
					class="form-control"
					maxlength={QUESTION_MAX}
					placeholder="Longueur de liaison, code erreur, entretien…"
					autocomplete="off"
					required
				/>
				<button type="submit" class="btn yack-smart-btn" disabled={ask.pending > 0}>
					{ask.pending > 0 ? 'Recherche…' : 'Demander'}
				</button>
			</div>
		</form>

		{#if coverage?.exampleQuestion}
			<p class="yack-smart-doc__example">
				Exemple :
				<button type="button" class="yack-smart-doc__example-btn" onclick={() => askExample(coverage.exampleQuestion ?? '')} data-test-documentation-example>
					{coverage.exampleQuestion}
				</button>
			</p>
		{:else if coverage && coverage.notices.length === 0}
			<p class="yack-smart-panel__note">Aucune notice de cet appareil n'est encore chargée dans la documentation.</p>
		{/if}

		{#if issue}
			<div class="alert alert-danger mt-3 mb-0" role="alert">{issue}</div>
		{/if}

		{#if result}
			<div class="yack-smart-panel__result" role="status" aria-live="polite" data-test-documentation-answer>
				<p class="yack-smart-doc__question">Votre question : {result.question}</p>
				<p class="yack-smart-doc__answer">{result.answer}</p>
				{#each result.sources as source (source.source + source.page)}
					<span class="yack-smart-panel__source">{sourceLabel(source)}</span>
				{/each}
			</div>
		{/if}
	{/if}
</section>
