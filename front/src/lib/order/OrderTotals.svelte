<script lang="ts">
	// Totals breakdown of a cart / order (yack.totals): goods excl. tax, promotion, escompte
	// (on the amount excl. tax, before VAT), shipping excl. tax, VAT, total incl. tax.
	// Amounts come from the API as they are (no computation here): .yack-totals of _checkout.scss.
	import { formatPrice } from '#lib/format.js';
	import type { OrderTotals, YackOrder } from './types.js';

	let {
		totals,
		escompte,
		shippingLabel = 'Livraison (HT)',
		showShipping = true
	}: {
		totals: OrderTotals;
		escompte: YackOrder['escompte'];
		shippingLabel?: string;
		/** Hide the shipping line (e.g. cart without shipment). */
		showShipping?: boolean;
	} = $props();
</script>

<dl class="yack-totals" data-test-order-totals>
	<div class="yack-totals__row">
		<dt>Sous-total HT</dt>
		<dd data-test-items-excl-tax>{formatPrice(totals.itemsExclTax)}</dd>
	</div>
	{#if totals.promotion !== 0}
		<div class="yack-totals__row yack-totals__row--discount">
			<dt>Remise</dt>
			<dd data-test-promotion>{formatPrice(totals.promotion)}</dd>
		</div>
	{/if}
	{#if escompte && totals.escompte !== 0}
		<div class="yack-totals__row yack-totals__row--discount" data-test-escompte>
			<dt>{escompte.label} <small>(sur le HT)</small></dt>
			<dd data-test-escompte-amount>{formatPrice(totals.escompte)}</dd>
		</div>
	{/if}
	{#if showShipping}
		<div class="yack-totals__row">
			<dt>{shippingLabel}</dt>
			<dd data-test-shipping-excl-tax>{formatPrice(totals.shippingExclTax)}</dd>
		</div>
	{/if}
	<div class="yack-totals__row yack-totals__row--subtotal">
		<dt>Total HT</dt>
		<dd data-test-total-excl-tax>{formatPrice(totals.totalExclTax)}</dd>
	</div>
	<div class="yack-totals__row">
		<dt>TVA</dt>
		<dd data-test-tax-total>{formatPrice(totals.taxTotal)}</dd>
	</div>
	<div class="yack-totals__row yack-totals__row--grand">
		<dt>Total TTC</dt>
		<dd data-test-order-total>{formatPrice(totals.total)}</dd>
	</div>
</dl>
