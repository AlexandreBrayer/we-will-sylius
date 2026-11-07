<script lang="ts">
	// Payment terms of a placed order (port of templates/shop/yack/order/_payment_instruction.html.twig):
	// bank transfer → Yack's bank details (never the customer's), reference and due date ("Virement reçu"
	// once paid); card → payment accepted.
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
	{#if instruction.paid}
		<div class="yack-callout yack-callout--success" data-test-payment-instruction="virement">
			<span class="yack-callout__title">Virement reçu</span>
			Merci, votre règlement de {formatPrice(instruction.amount)} a été enregistré par Yack.
		</div>
	{:else}
		<div class="yack-callout" data-test-payment-instruction="virement">
			<span class="yack-callout__title">{methodName ?? 'Virement'}</span>
			Merci d'effectuer votre virement de <strong>{formatPrice(instruction.amount)}</strong>
			{#if dueDate}avant le <strong data-test-due-date>{dueDate}</strong>{/if}.
			{#if instruction.bankAccount}
				<dl class="yack-rib">
					<dt>Titulaire</dt>
					<dd>{instruction.bankAccount.holder}</dd>
					<dt>Banque</dt>
					<dd>{instruction.bankAccount.bank}</dd>
					<dt>IBAN</dt>
					<dd data-test-iban>{instruction.bankAccount.iban}</dd>
					<dt>BIC</dt>
					<dd>{instruction.bankAccount.bic}</dd>
					<dt>Référence à indiquer</dt>
					<dd><strong data-test-payment-reference>{instruction.reference}</strong></dd>
				</dl>
			{/if}
		</div>
	{/if}
{:else if instruction?.kind === 'carte'}
	<div class="yack-callout yack-callout--success" data-test-payment-instruction="carte">
		<span class="yack-callout__title">Paiement accepté</span>
		{formatPrice(instruction.amount)} réglés par carte bancaire.
		<div class="small text-body-secondary mt-1">Simulation POC : aucune transaction réelle n'a été effectuée.</div>
	</div>
{/if}
