<script lang="ts">
	// Payment terms of a placed order (port of templates/shop/yack/order/_payment_instruction.html.twig):
	// bank transfer → RIB, reference and due date ("Virement reçu" once paid); card → payment accepted.
	import { formatDate, formatPrice } from '#lib/format.js';
	import type { PaymentInstruction } from './types.js';

	let {
		instruction,
		methodName
	}: {
		instruction: PaymentInstruction | null;
		/** Name of the payment method ("Virement sous 15 jours"). */
		methodName: string | null;
	} = $props();

	const dueDate = $derived(instruction?.dueDate ? formatDate(instruction.dueDate) : null);
</script>

{#if instruction?.kind === 'virement'}
	<div class="yack-callout" data-test-payment-instruction="virement">
		{#if instruction.paid}
			<p class="mb-0"><strong>Virement reçu</strong> — merci, votre règlement de {formatPrice(instruction.amount)} a été enregistré par Yack.</p>
		{:else}
			<p class="mb-2">
				<strong>{methodName ?? 'Virement'}</strong> — merci d'effectuer votre virement de <strong>{formatPrice(instruction.amount)}</strong>
				{#if dueDate}avant le <strong data-test-due-date>{dueDate}</strong>{/if}.
			</p>
			{#if instruction.bankAccount}
				<dl class="row yack-rib mb-0 small">
					<dt class="col-sm-4">Titulaire</dt>
					<dd class="col-sm-8">{instruction.bankAccount.holder}</dd>
					<dt class="col-sm-4">Banque</dt>
					<dd class="col-sm-8">{instruction.bankAccount.bank}</dd>
					<dt class="col-sm-4">IBAN</dt>
					<dd class="col-sm-8" data-test-iban>{instruction.bankAccount.iban}</dd>
					<dt class="col-sm-4">BIC</dt>
					<dd class="col-sm-8">{instruction.bankAccount.bic}</dd>
					<dt class="col-sm-4">Référence à indiquer</dt>
					<dd class="col-sm-8"><strong data-test-payment-reference>{instruction.reference}</strong></dd>
				</dl>
			{/if}
		{/if}
	</div>
{:else if instruction?.kind === 'carte'}
	<div class="yack-callout yack-callout--success" data-test-payment-instruction="carte">
		<strong>Paiement accepté</strong> — {formatPrice(instruction.amount)} réglés par carte bancaire.
		<div class="small text-body-secondary">Simulation POC : aucune transaction réelle n'a été effectuée.</div>
	</div>
{/if}
