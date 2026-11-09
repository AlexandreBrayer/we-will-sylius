<script lang="ts">
	// Mini-cart drawer (ECOM), opened by the "Panier" links of the header and of the tab bar.
	// Lines and totals come from getCart (same data as /panier), fetched when the drawer opens. The total is
	// the goods alone ("Sous-total HT", first row of « Votre commande »): shipping comes with the checkout.
	// Without JavaScript the "Panier" links simply lead to /panier.
	import { tick, untrack } from 'svelte';
	import { page } from '$app/state';
	import { formatPrice } from '#lib/format.js';
	import { getCart } from '#lib/remote/cart.remote.js';
	import Icon from './Icon.svelte';

	let { open = $bindable(false), count }: { open: boolean; count: number } = $props();

	let panel = $state<HTMLElement>();
	let opener: HTMLElement | null = null;
	const cart = $derived(open ? getCart() : null);

	// On each opening: focus the panel, refresh a cart already loaded (it may be stale).
	$effect(() => {
		if (!open) return;
		untrack(() => {
			opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
			const query = getCart();
			if (query.ready) void query.refresh();
			void tick().then(() => panel?.focus());
		});
	});

	// Close on navigation.
	$effect(() => {
		void page.url.pathname;
		untrack(close);
	});

	function close() {
		if (!open) return;
		open = false;
		opener?.focus();
	}
</script>

<svelte:window onkeydown={(event) => open && event.key === 'Escape' && close()} />

{#if open}
	<div class="yack-drawer-backdrop" role="presentation" onclick={close}></div>
	<div class="yack-drawer" role="dialog" aria-modal="true" aria-labelledby="yack-minicart-title" tabindex="-1" bind:this={panel} data-test-mini-cart>
		<div class="yack-drawer__head">
			<span id="yack-minicart-title" class="yack-drawer__title">Mon panier ({count})</span>
			<button type="button" class="yack-drawer__close" aria-label="Fermer le panier" onclick={close}><Icon name="close" /></button>
		</div>
		{#await cart}
			<p class="yack-drawer__body yack-drawer__empty" aria-live="polite">Chargement du panier…</p>
		{:then view}
			{#if !view || view.lines.length === 0}
				<div class="yack-drawer__body yack-drawer__empty" data-test-mini-cart-empty>
					<Icon name="cart" size="lg" />
					<p>Votre panier est vide.</p>
					<a class="btn btn-ghost" href="/produits">Voir nos produits</a>
				</div>
			{:else}
				<ul class="yack-drawer__body yack-minicart__lines">
					{#each view.lines as line (line.id)}
						<li class="yack-minicart__line" data-test-mini-cart-line={line.code}>
							{#if line.url}
								<a class="yack-minicart__name" href={line.url}>{line.name}</a>
							{:else}
								<span class="yack-minicart__name">{line.name}</span>
							{/if}
							<span class="yack-minicart__meta">
								{line.code ? `Réf. ${line.code} · ` : ''}{line.quantity} × {formatPrice(line.unitPrice)} HT
							</span>
							<strong class="yack-minicart__subtotal">{formatPrice(line.subtotal)}</strong>
						</li>
					{/each}
				</ul>
				<div class="yack-drawer__foot">
					<div class="yack-minicart__total">
						<span>Sous-total HT <small>(hors livraison)</small></span>
						<strong data-test-mini-cart-total>{formatPrice(view.totals.itemsExclTax)}</strong>
					</div>
					<a class="btn btn-primary w-100" href="/commande/adresse" data-test-mini-cart-checkout>Commander</a>
					<a class="btn btn-ghost w-100" href="/panier" data-test-mini-cart-view>Voir le panier</a>
				</div>
			{/if}
		{:catch}
			<div class="yack-drawer__body yack-drawer__empty">
				<p>Le panier n’a pas pu être chargé.</p>
				<a class="btn btn-ghost" href="/panier">Voir le panier</a>
			</div>
		{/await}
	</div>
{/if}
