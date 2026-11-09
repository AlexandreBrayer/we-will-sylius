<script lang="ts">
	// Payment of a placed order still to pay: after a refused / abandoned card payment ("Paiement non
	// abouti") or from the account (order awaiting its card payment). The order is kept: retry by card
	// (saved card in one click, or a card typed on the provider's page) or switch to a bank transfer
	// (escompte, VAT and total recomputed by Sylius, then the confirmation page with the RIB).
	import { formatPrice } from '#lib/format.js';
	import Icon from '#lib/layout/Icon.svelte';
	import OrderTotals from '#lib/order/OrderTotals.svelte';
	import { changePaymentMethod, getPayableOrder, payByCard } from '#lib/remote/payment.remote.js';

	let { params } = $props();
	const view = $derived(await getPayableOrder(params.token));
	const order = $derived(view.order);
	// The query sends a paid order to its confirmation page: here the order is payable.
	const payable = $derived(order.yack.payable!);
	const attempt = $derived(payable.lastAttempt);
	const cards = $derived(view.savedCards.filter((card) => !card.expired));
	const cardMethod = $derived(payable.methods.find((method) => method.card) ?? null);
	const transferMethods = $derived(payable.methods.filter((method) => !method.card));
	const onCard = $derived(order.yack.paymentMethod?.card ?? false);
	const cardError = $derived(payByCard.fields.allIssues()?.[0]?.message);
	const switchError = $derived(changePaymentMethod.fields.allIssues()?.[0]?.message);
</script>

<svelte:head>
	<title>Règlement de la commande n° {order.number} | Clik&amp;Yack</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="container mt-4 mb-5">
	<div class="row g-4 g-lg-5">
		<div class="col-12 col-lg-8">
			{#if attempt}
				<div class="yack-confirm mb-4" data-test-payment-failed={attempt.status}>
					<span class="yack-round-icon"><Icon name="close" /></span>
					<h1 class="h2">Paiement non abouti</h1>
					<p class="mb-1" data-test-payment-failure-message>{attempt.message}{attempt.card ? ` (${attempt.card.label})` : ''}.</p>
					<p class="mb-0">Votre commande <strong data-test-order-number>n° {order.number}</strong> est conservée et aucun débit n'a été effectué. Choisissez comment la régler.</p>
				</div>
			{:else}
				<div class="yack-page-head">
					<h1>Régler votre commande n° {order.number}</h1>
					<p>Votre commande est enregistrée ; elle sera préparée une fois réglée.</p>
				</div>
			{/if}

			{#if cardMethod}
				<section class="yack-account-card" data-test-retry-card>
					<h2 class="yack-account-card__title">{attempt ? 'Réessayer par carte bancaire' : 'Payer par carte bancaire'}</h2>
					{#if cardError}<div class="alert alert-danger" role="alert" data-test-card-error>{cardError}</div>{/if}
					<form {...payByCard.enhance(async ({ submit }) => void (await submit()))}>
						<input {...payByCard.fields.token.as('hidden', order.tokenValue)} />
						{#each cards as card, index (card.id)}
							<div class="form-check">
								<input class="form-check-input" id="retry-card-{card.id}" {...payByCard.fields.savedCard.as('radio', String(card.id), index === 0)} data-test-saved-card={card.id} />
								<label class="form-check-label" for="retry-card-{card.id}">Payer avec {card.label} <span class="text-muted small">exp. {card.expiry}</span></label>
							</div>
						{/each}
						<div class="form-check mb-3">
							<input class="form-check-input" id="retry-card-new" {...payByCard.fields.savedCard.as('radio', 'new', cards.length === 0)} data-test-saved-card="new" />
							<label class="form-check-label" for="retry-card-new">{cards.length > 0 ? 'Une autre carte' : 'Saisir ma carte'} <span class="text-muted small">sur la page de paiement sécurisée</span></label>
						</div>
						<button class="btn btn-primary" type="submit" disabled={payByCard.pending > 0} data-test-pay-by-card>
							Payer {formatPrice(onCard ? payable.amount : order.yack.totals.total)} par carte
						</button>
						{#if !onCard}<div class="small text-body-secondary mt-2">Le total est recalculé selon l'escompte de la carte ({cardMethod.escompteLabel ?? 'prix normal'}).</div>{/if}
					</form>
				</section>
			{/if}

			{#if transferMethods.length > 0}
				<section class="yack-account-card" data-test-switch-transfer>
					<h2 class="yack-account-card__title">Payer par virement</h2>
					<p class="small text-body-secondary">Même commande, sans nouvelle validation : l'escompte et le total sont recalculés selon le mode choisi, les coordonnées bancaires de Yack s'affichent ensuite et vous sont envoyées par e-mail.</p>
					{#if switchError}<div class="alert alert-danger" role="alert" data-test-switch-error>{switchError}</div>{/if}
					<form {...changePaymentMethod.enhance(async ({ submit }) => void (await submit()))}>
						<input {...changePaymentMethod.fields.token.as('hidden', order.tokenValue)} />
						{#each transferMethods as method, index (method.code)}
							<div class="form-check">
								<input class="form-check-input" id="method-{method.code}" {...changePaymentMethod.fields.paymentMethod.as('radio', method.code, index === 0)} data-test-transfer-method={method.code} />
								<label class="form-check-label d-inline-flex flex-wrap gap-2 align-items-center" for="method-{method.code}">
									{method.name}
									{#if method.escompteLabel}<span class="yack-tag yack-tag--secondary">{method.escompteLabel}</span>{:else}<span class="yack-tag yack-tag--muted">Prix normal</span>{/if}
								</label>
							</div>
						{/each}
						<button class="btn btn-outline-primary mt-3" type="submit" disabled={changePaymentMethod.pending > 0} data-test-switch-button>Choisir le virement</button>
					</form>
				</section>
			{/if}
		</div>

		<div class="col-12 col-lg-4">
			<aside class="yack-summary" data-test-payable-summary>
				<h2 class="yack-summary__title">Commande n° {order.number}</h2>
				<ul class="yack-summary__lines">
					{#each view.lines as line (line.id)}
						<li class="yack-summary__line">
							<span class="yack-summary__name">{line.name}<small>{line.code}</small></span>
							<span class="yack-summary__qty">× {line.quantity}</span>
							<span class="yack-summary__amount">{formatPrice(line.subtotal)}</span>
						</li>
					{/each}
				</ul>
				<OrderTotals totals={order.yack.totals} escompte={order.yack.escompte} showShipping={order.shipments.length > 0} />
				<p class="yack-summary__meta">
					{#if order.yack.paymentMethod}Paiement : {order.yack.paymentMethod.name}<br />{/if}
					<a href="/compte/commandes/{order.number}">Voir la commande dans mon compte</a>
				</p>
			</aside>
		</div>
	</div>
</div>
