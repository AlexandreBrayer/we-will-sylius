<script lang="ts">
	// Shop chrome (header, profile strip, footer, mobile tab bar, mini-cart) around every page,
	// error pages included (unknown URLs keep the shop header). The builder (/cms-admin) has its own chrome. Data: remote queries, awaited
	// during SSR; the header widgets follow refreshes (login, cart mutations…).
	import { page } from '$app/state';
	import DemoBar from '#lib/layout/DemoBar.svelte';
	import MiniCart from '#lib/layout/MiniCart.svelte';
	import ProfileStrip from '#lib/layout/ProfileStrip.svelte';
	import ShopFooter from '#lib/layout/ShopFooter.svelte';
	import ShopHeader from '#lib/layout/ShopHeader.svelte';
	import TabBar from '#lib/layout/TabBar.svelte';
	import { assistanceEnabled } from '#lib/remote/assistance.remote.js';
	import { getCartSummary } from '#lib/remote/cart.remote.js';
	import { getDemoBar } from '#lib/remote/demo.remote.js';
	import { getPublishedPages } from '#lib/remote/cms.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';

	let { children } = $props();

	const shop = $derived(!page.route.id?.startsWith('/cms-admin'));

	/** Mini-cart drawer (ECOM), opened from the header and the tab bar; plain /panier link without JS. */
	let miniCartOpen = $state(false);
	const openMiniCart = (event: MouseEvent) => {
		if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
		event.preventDefault();
		miniCartOpen = true;
	};
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
	{@const viewer = await getViewer()}
	{@const cart = await getCartSummary()}
	{@const demoBar = await getDemoBar()}
	{@const assistance = await assistanceEnabled()}
	<div class="yack-shell d-flex flex-column min-vh-100">
		{#if demoBar}<DemoBar {viewer} />{/if}
		<ShopHeader {viewer} {cart} published={pages.map((p) => p.slug)} {assistance} onOpenCart={openMiniCart} />
		<ProfileStrip {viewer} />
		<main class="pt-0">
			{@render children()}
		</main>
		<ShopFooter pages={pages.filter((p) => p.slug !== 'accueil')} />
		<TabBar {viewer} {cart} onOpenCart={openMiniCart} />
	</div>
	{#if cart}
		<MiniCart bind:open={miniCartOpen} count={cart.itemsCount} />
	{/if}
{:else}
	{@render children()}
{/if}
