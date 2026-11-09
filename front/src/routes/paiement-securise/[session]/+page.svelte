<script lang="ts">
	// Hosted payment page of the simulated provider (Monext / Payline style, ADR 0009). Card checks
	// (brand, Luhn, expiry, CVV) on the page server (#lib/remote/payment.remote.ts): the card number
	// and CVV never reach Sylius, only the masked card. Steps: card → 3-D Secure (bank screen, code
	// shown) → back to Yack's return page; "Annuler" goes back too.
	import { TEST_CARDS, BRAND_LABELS, cardLength, detectBrand, digitsOf, formatAmount, formatCardNumber, formatExpiry, luhn } from '#lib/payment/card.js';
	import { cancelHostedPayment, confirmChallenge, getHostedPayment, payWithCard } from '#lib/remote/payment.remote.js';

	let { params } = $props();
	const session = $derived(await getHostedPayment(params.session));
	const amount = $derived(formatAmount(session.amount, session.currencyCode));
	const returnPath = $derived(new URL(session.returnUrl).pathname);

	const number = $derived(payWithCard.fields._cardNumber.value() ?? '');
	const digits = $derived(digitsOf(number));
	const brand = $derived(detectBrand(digits));
	const complete = $derived(brand !== null && digits.length === cardLength(brand));
	const formError = $derived(payWithCard.fields.allIssues()?.find((issue) => issue.path.length === 0)?.message);
	const challengeError = $derived(confirmChallenge.fields.allIssues()?.[0]?.message);

	const testExpiry = `12/${String((new Date().getFullYear() + 3) % 100).padStart(2, '0')}`;

	function useTestCard(card: (typeof TEST_CARDS)[number]) {
		payWithCard.fields._cardNumber.set(card.number);
		payWithCard.fields.expiry.set(testExpiry);
		payWithCard.fields._cvv.set('123');
	}

	const fieldError = (issues: { message: string }[] | undefined) => issues?.[0]?.message;
</script>

<svelte:head>
	<title>Paiement sécurisé : {session.merchant}</title>
</svelte:head>

<div class="mnx-page">
	<header class="mnx-header">
		<div class="mnx-logo" aria-label="Monext (simulation)">mon<span>e</span>xt <small>payline</small></div>
		<div class="mnx-secure">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
			Paiement sécurisé
		</div>
	</header>
	<div class="mnx-demo" data-test-demo-banner>Environnement de démonstration, aucun débit</div>

	<main class="mnx-main">
		<section class="mnx-panel {session.step === 'challenge' ? 'mnx-bank' : ''}" data-test-hosted-step={session.step}>
			<div class="mnx-merchant">
				<div>
					<div class="mnx-merchant__name" data-test-merchant>{session.merchant}</div>
					<div class="mnx-merchant__ref">Commande n° {session.orderNumber} · réf. {session.reference}</div>
				</div>
				<div class="mnx-amount" data-test-hosted-amount>{amount}<small>TTC</small></div>
			</div>

			{#if session.step === 'card'}
				<h1>Payer par carte bancaire</h1>
				{#if formError}<div class="mnx-alert" role="alert">{formError}</div>{/if}
				<form {...payWithCard.enhance(async ({ submit }) => void (await submit()))} autocomplete="off" novalidate>
					<input {...payWithCard.fields.session.as('hidden', session.id)} />
					<div class="mnx-field">
						<label for="card-number">Numéro de carte</label>
						<div class="mnx-input-wrap">
							<input
								class="mnx-input mnx-input--card"
								id="card-number"
								{...payWithCard.fields._cardNumber.as('text')}
								oninput={(event) => payWithCard.fields._cardNumber.set(formatCardNumber(event.currentTarget.value))}
								inputmode="numeric"
								autocomplete="cc-number"
								placeholder="1234 5678 9012 3456"
								data-test-card-number
							/>
							{#if brand}<span class="mnx-brand" data-test-card-brand={brand}>{BRAND_LABELS[brand]}</span>{/if}
						</div>
						{#if fieldError(payWithCard.fields._cardNumber.issues())}
							<div class="mnx-error" data-test-card-number-error>{fieldError(payWithCard.fields._cardNumber.issues())}</div>
						{:else if complete && !luhn(digits)}
							<div class="mnx-error">Numéro de carte invalide : vérifiez la saisie.</div>
						{:else if complete}
							<div class="mnx-hint mnx-hint--ok">Numéro valide</div>
						{:else}
							<div class="mnx-hint">Visa, Mastercard ou American Express</div>
						{/if}
					</div>
					<div class="mnx-row">
						<div class="mnx-field">
							<label for="card-expiry">Date d'expiration</label>
							<input
								class="mnx-input"
								id="card-expiry"
								{...payWithCard.fields.expiry.as('text')}
								oninput={(event) => payWithCard.fields.expiry.set(formatExpiry(event.currentTarget.value))}
								inputmode="numeric"
								autocomplete="cc-exp"
								placeholder="MM/AA"
								data-test-card-expiry
							/>
							{#if fieldError(payWithCard.fields.expiry.issues())}<div class="mnx-error">{fieldError(payWithCard.fields.expiry.issues())}</div>{/if}
						</div>
						<div class="mnx-field">
							<label for="card-cvv">Cryptogramme</label>
							<input
								class="mnx-input"
								id="card-cvv"
								{...payWithCard.fields._cvv.as('password')}
								inputmode="numeric"
								autocomplete="cc-csc"
								maxlength={brand === 'amex' ? 4 : 3}
								placeholder={brand === 'amex' ? '4 chiffres' : '3 chiffres'}
								data-test-card-cvv
							/>
							{#if fieldError(payWithCard.fields._cvv.issues())}<div class="mnx-error">{fieldError(payWithCard.fields._cvv.issues())}</div>{/if}
						</div>
					</div>
					<label class="mnx-check">
						<input {...payWithCard.fields.saveCard.as('checkbox')} data-test-save-card />
						<span>Enregistrer cette carte pour mes prochains achats chez {session.merchant} (seuls la marque, les 4 derniers chiffres et la date d'expiration sont conservés par le marchand)</span>
					</label>
					<button class="mnx-btn" type="submit" disabled={payWithCard.pending > 0} data-test-hosted-pay>Payer {amount}</button>
				</form>
			{:else if session.step === 'challenge'}
				<div class="mnx-bank__head">
					<span>Banque de démonstration · 3-D Secure</span>
					<span>{session.card?.brandLabel ?? ''}</span>
				</div>
				<h1>Confirmez votre paiement</h1>
				<p>
					Paiement de <strong>{amount}</strong> chez {session.merchant} avec la carte <strong data-test-challenge-card>{session.card?.label ?? ''}</strong>.
					Saisissez le code reçu par SMS au 06 •• •• •• 42.
				</p>
				<div class="mnx-sms" data-test-challenge-code>SMS (démonstration) : votre code est <strong>{session.challengeCode}</strong></div>
				{#if challengeError}<div class="mnx-alert" role="alert">{challengeError}</div>{/if}
				<form {...confirmChallenge.enhance(async ({ submit }) => void (await submit()))} autocomplete="off">
					<input {...confirmChallenge.fields.session.as('hidden', session.id)} />
					<div class="mnx-field">
						<label for="challenge-code">Code de confirmation</label>
						<input class="mnx-input" id="challenge-code" {...confirmChallenge.fields.code.as('text')} inputmode="numeric" maxlength={12} data-test-challenge-input />
					</div>
					<button class="mnx-btn mnx-btn--bank" type="submit" disabled={confirmChallenge.pending > 0} data-test-challenge-submit>Valider</button>
				</form>
			{:else}
				<div class="mnx-result {session.step === 'accepted' ? 'mnx-result--ok' : ''}">
					<h1>{session.step === 'accepted' ? 'Paiement accepté' : session.step === 'refused' ? 'Paiement refusé' : 'Paiement annulé'}</h1>
					{#if session.refusalReason}<p>{session.refusalReason}</p>{/if}
					<a class="mnx-btn" href={returnPath} data-sveltekit-reload>Retourner chez {session.merchant}</a>
				</div>
			{/if}

			{#if session.step === 'card' || session.step === 'challenge'}
				<form {...cancelHostedPayment.enhance(async ({ submit }) => void (await submit()))}>
					<input {...cancelHostedPayment.fields.session.as('hidden', session.id)} />
					<button class="mnx-link" type="submit" data-test-hosted-cancel>Annuler et retourner chez {session.merchant}</button>
				</form>
			{/if}
		</section>

		<aside class="mnx-panel" data-test-test-cards>
			<h2>Cartes de test</h2>
			<table class="mnx-cards">
				<thead>
					<tr><th>Numéro</th><th>Résultat</th><th></th></tr>
				</thead>
				<tbody>
					{#each TEST_CARDS as card (card.number)}
						<tr>
							<td><code>{card.number}</code><br /><small>{BRAND_LABELS[card.brand]}</small></td>
							<td>{card.outcome}</td>
							<td>
								{#if session.step === 'card'}
									<button class="mnx-use" type="button" onclick={() => useTestCard(card)} data-test-use-card={digitsOf(card.number).slice(-4)}>Utiliser</button>
								{/if}
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
			<p class="mnx-note">Date d'expiration future quelconque (ex. {testExpiry}), cryptogramme quelconque (ex. 123). Code 3-D Secure : 123456, tout autre code refuse le paiement.</p>
		</aside>
	</main>

	<footer class="mnx-footer">
		Page de paiement simulée (prestataire de type Monext / Payline). Le numéro de carte n'est transmis ni enregistré chez {session.merchant} : seuls la marque, les 4 derniers chiffres et la date d'expiration lui parviennent.
	</footer>
</div>
