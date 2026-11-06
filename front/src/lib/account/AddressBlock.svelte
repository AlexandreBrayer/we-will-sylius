<script lang="ts">
	// Postal address (Sylius shop "shared/address" markup): name, company, street, city, country, phone.
	import type { OrderAddress } from '#lib/order/types.js';

	let { address }: { address: OrderAddress } = $props();

	const regions = new Intl.DisplayNames(['fr'], { type: 'region' });
</script>

<address class="mb-0 fst-normal" data-test-address>
	<strong>{address.firstName} {address.lastName}</strong><br />
	{#if address.company}{address.company}<br />{/if}
	{address.street}<br />
	{address.postcode} {address.city}<br />
	{regions.of(address.countryCode) ?? address.countryCode}
	{#if address.phoneNumber}<br /><span class="text-body-secondary">{address.phoneNumber}</span>{/if}
</address>
