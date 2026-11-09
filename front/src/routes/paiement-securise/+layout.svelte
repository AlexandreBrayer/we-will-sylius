<script lang="ts">
	// Hosted payment page of the simulated provider (ADR 0009): its own look (Monext / Payline style,
	// #lib/payment/simulator.css), not Yack's chrome.
	import '#lib/payment/simulator.css';

	let { children } = $props();
</script>

<svelte:head>
	<meta name="robots" content="noindex" />
</svelte:head>

{@render children()}
