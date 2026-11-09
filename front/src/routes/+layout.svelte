<script lang="ts">
	// Shop chrome (header, profile strip, footer, mobile tab bar, mini-cart) around every page,
	// error pages included (unknown URLs keep the shop header). The builder (/cms-admin) has its own chrome. Data: remote queries, awaited
	// during SSR; the header widgets follow refreshes (login, cart mutations…).
	import { onMount } from 'svelte';
	import { afterNavigate, beforeNavigate, goto } from '$app/navigation';
	import { navigating, page } from '$app/state';
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
	import { createNavigationGuard } from '#lib/layout/navigationGuard.js';

	let { children } = $props();

	// Une seule navigation à la fois (voir navigationGuard.ts) : un clic pendant une navigation en cours
	// est ignoré (même adresse) ou mis en attente (autre adresse), au lieu de superposer deux pages.
	const guard = createNavigationGuard({
		pending: () => navigating.to?.url ?? null,
		location: () => new URL(window.location.href),
		go: (url) => void goto(url.pathname + url.search + url.hash),
		reload: (url) => window.location.assign(url.href),
		now: () => performance.now()
	});
	beforeNavigate(() => guard.begin());
	afterNavigate(() => guard.settle());
	onMount(() => {
		const onClick = (event: MouseEvent) => {
			const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
			guard.onClick(event, anchor as HTMLAnchorElement | SVGAElement | null);
		};
		const onPopState = () => guard.drop();
		document.addEventListener('click', onClick, true);
		window.addEventListener('popstate', onPopState);
		return () => {
			document.removeEventListener('click', onClick, true);
			window.removeEventListener('popstate', onPopState);
		};
	});

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
		{#if navigating.to}<div class="yack-nav-progress" role="progressbar" aria-label="Chargement de la page" data-test-nav-progress></div>{/if}
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
