<script lang="ts">
	import ShopFooter from '#lib/layout/ShopFooter.svelte';
	import ShopHeader from '#lib/layout/ShopHeader.svelte';

	let { data, children } = $props();
</script>

<svelte:head>
	{#if data.chrome}
		<!-- Charte Yack = feuilles de style de la boutique Sylius (Bootstrap + assets/shop/styles/yack.scss), servies par nginx -->
		<link rel="stylesheet" href="/build/shop/shop-entry.css" />
		<link rel="stylesheet" href="/build/app/shop/app-shop-entry.css" />
	{/if}
	<link rel="icon" href="/yack/logo-yack.png" />
</svelte:head>

{#if data.chrome}
	<div class="d-flex flex-column min-vh-100">
		<ShopHeader viewer={data.viewer} cart={data.chrome.cart} servicesPage={data.chrome.servicesPage} />
		<main class="pt-0">
			{@render children()}
		</main>
		<ShopFooter pages={data.chrome.footerPages} />
	</div>
{:else}
	{@render children()}
{/if}
