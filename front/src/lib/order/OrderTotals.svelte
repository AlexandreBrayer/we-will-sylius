<script lang="ts">
	// Totals breakdown of a cart / order (yack.totals): goods excl. tax, promotion, escompte
	// (on the amount excl. tax, before VAT), shipping excl. tax, VAT, total incl. tax.
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

<div data-test-order-totals>
	<div class="hstack gap-2 mb-2">
		<div>Sous-total HT :</div>
		<div class="ms-auto text-end" data-test-items-excl-tax>{formatPrice(totals.itemsExclTax)}</div>
	</div>
	{#if totals.promotion !== 0}
		<div class="hstack gap-2 mb-2">
			<div>Remise :</div>
			<div class="ms-auto text-end text-success" data-test-promotion>{formatPrice(totals.promotion)}</div>
		</div>
	{/if}
	{#if escompte && totals.escompte !== 0}
		<div class="hstack gap-2 mb-2" data-test-escompte>
			<div>{escompte.label} <small class="text-body-tertiary">(sur le HT)</small> :</div>
			<div class="ms-auto text-end text-success" data-test-escompte-amount>{formatPrice(totals.escompte)}</div>
		</div>
	{/if}
	{#if showShipping}
		<div class="hstack gap-2 mb-2">
			<div>{shippingLabel} :</div>
			<div class="ms-auto text-end" data-test-shipping-excl-tax>{formatPrice(totals.shippingExclTax)}</div>
		</div>
	{/if}
	<div class="hstack gap-2 mb-2 border-top pt-2">
		<div>Total HT :</div>
		<div class="ms-auto text-end" data-test-total-excl-tax>{formatPrice(totals.totalExclTax)}</div>
	</div>
	<div class="hstack gap-2 mb-2">
		<div>TVA :</div>
		<div class="ms-auto text-end" data-test-tax-total>{formatPrice(totals.taxTotal)}</div>
	</div>
	<div class="hstack gap-2 border-top pt-3 mt-3">
		<div class="h5 mb-0">Total TTC :</div>
		<div class="ms-auto h5 mb-0 text-end" data-test-order-total>{formatPrice(totals.total)}</div>
	</div>
</div>
