<script lang="ts">
	import ShopHeader from '$lib/layout/ShopHeader.svelte';
	import ShopFooter from '$lib/layout/ShopFooter.svelte';

	let { data, children } = $props();
</script>

<svelte:head>
	<!-- Charte Yack = feuilles de style de la boutique Sylius (Bootstrap + assets/shop/styles/yack.scss) -->
	<link rel="stylesheet" href="{data.syliusUrl}/build/shop/shop-entry.css" />
	<link rel="stylesheet" href="{data.syliusUrl}/build/app/shop/app-shop-entry.css" />
	<link rel="icon" href="{data.syliusUrl}/yack/logo-yack.png" />
</svelte:head>

<div class="d-flex flex-column min-vh-100">
	<ShopHeader syliusUrl={data.syliusUrl} />
	<main class="pt-0">
		{@render children()}
	</main>
	<ShopFooter syliusUrl={data.syliusUrl} pages={data.footerPages} />
</div>
