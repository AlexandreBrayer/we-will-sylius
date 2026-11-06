<script lang="ts">
	// Shop chrome (header, footer) around every page, error pages included (unknown URLs keep the
	// shop header). The builder (/cms-admin) has its own chrome. Data: remote queries, awaited
	// during SSR; the header widgets follow refreshes (login, cart mutations…).
	import { page } from '$app/state';
	import ShopFooter from '#lib/layout/ShopFooter.svelte';
	import ShopHeader from '#lib/layout/ShopHeader.svelte';
	import { getCartSummary } from '#lib/remote/cart.remote.js';
	import { getPublishedPages } from '#lib/remote/cms.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { children } = $props();

	const shop = $derived(!page.route.id?.startsWith('/cms-admin'));
</script>

<svelte:head>
	{#if shop}
		<!-- Charte Yack = feuilles de style de la boutique Sylius (Bootstrap + assets/shop/styles/yack.scss), servies par nginx -->
		<link rel="stylesheet" href="/build/shop/shop-entry.css" />
		<link rel="stylesheet" href="/build/app/shop/app-shop-entry.css" />
	{/if}
	<link rel="icon" href="/yack/logo-yack.png" />
</svelte:head>

{#if shop}
	{@const pages = await getPublishedPages()}
	<div class="d-flex flex-column min-vh-100">
		<ShopHeader
			viewer={await getViewer()}
			cart={await getCartSummary()}
			servicesPage={pages.some((p) => p.slug === 'nos-services')}
		/>
		<main class="pt-0">
			{@render children()}
		</main>
		<ShopFooter pages={pages.filter((p) => p.slug !== 'accueil')} />
	</div>
{:else}
	{@render children()}
{/if}
