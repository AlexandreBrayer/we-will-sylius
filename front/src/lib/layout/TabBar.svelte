<script lang="ts">
	// Mobile tab bar (below md): Accueil, Catalogue, Pièces, SAV (straight to « La pièce par une photo »),
	// Compte, and Panier when the viewer can order (cart summary from getCartSummary, null otherwise). Plain
	// links: without JavaScript the Panier tab leads to /panier; with JavaScript it opens the mini-cart (`onOpenCart`).
	import { page } from '$app/state';
	import { NAMEPLATE_ANCHOR } from '#lib/sav/nameplate.js';
	import type { CartSummary, Viewer } from '#lib/types.js';
	import Icon, { type IconName } from './Icon.svelte';

	let { viewer, cart, onOpenCart }: { viewer: Viewer; cart: CartSummary | null; onOpenCart: (event: MouseEvent) => void } = $props();

	const tabs = $derived<{ href: string; label: string; icon: IconName; match: (path: string) => boolean }[]>([
		{ href: '/', label: 'Accueil', icon: 'home', match: (path) => path === '/' },
		{ href: '/produits', label: 'Catalogue', icon: 'grid', match: (path) => path.startsWith('/produits') || path.startsWith('/produit/') },
		{ href: '/pieces', label: 'Pièces', icon: 'part', match: (path) => path.startsWith('/pieces') },
		{ href: `/sav#${NAMEPLATE_ANCHOR}`, label: 'SAV', icon: 'camera', match: (path) => path === '/sav' || path.startsWith('/sav/') },
		{
			href: viewer.authenticated ? '/compte' : '/connexion',
			label: 'Compte',
			icon: 'user',
			match: (path) => path.startsWith('/compte') || path === '/connexion'
		}
	]);
</script>

<nav class="yack-tabbar" class:yack-tabbar--cart={cart !== null} aria-label="Navigation mobile" data-test-tab-bar>
	{#each tabs as tab (tab.label)}
		<a class="yack-tabbar__tab" href={tab.href} aria-current={tab.match(page.url.pathname) ? 'page' : undefined}>
			<Icon name={tab.icon} />
			<span>{tab.label}</span>
		</a>
	{/each}
	{#if cart}
		<a class="yack-tabbar__tab" href="/panier" aria-current={page.url.pathname === '/panier' ? 'page' : undefined} onclick={onOpenCart} data-test-tab-cart>
			<Icon name="cart" />
			{#if cart.itemsCount > 0}<span class="yack-tabbar__count">{cart.itemsCount}</span>{/if}
			<span>Panier</span>
		</a>
	{/if}
</nav>
