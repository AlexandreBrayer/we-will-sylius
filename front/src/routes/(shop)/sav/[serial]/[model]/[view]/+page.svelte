<script lang="ts">
	// Port of sav/exploded_view.html.twig (+ exploded-view Stimulus controller): numbered hotspots on the
	// drawing and parts table highlight each other on hover; price and "Ajouter" for ECOM accounts only.
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import PricingNotice from '#lib/catalog/PricingNotice.svelte';
	import { formatPrice } from '#lib/format.js';
	import { addToCart } from '#lib/remote/cart.remote.js';
	import { getExplodedView } from '#lib/remote/sav.remote.js';
	import { getViewer } from '#lib/remote/session.remote.js';
	import { savUrl, type SavPart } from '#lib/sav/types.js';

	let { params } = $props();
	const data = $derived(await getExplodedView({ serial: params.serial, model: params.model, view: params.view }));
	const view = $derived(data.view);
	const viewer = $derived(await getViewer());

	/** Marker hovered (drawing or table). */
	let active = $state<number | null>(null);
	/** Table rows by marker. */
	const rows: Record<number, HTMLTableRowElement> = {};

	/** Click on a hotspot: bring its table row into view (and its quantity field into focus). */
	function focusRow(marker: number) {
		const row = rows[marker];
		if (!row) return;
		row.scrollIntoView({ behavior: 'smooth', block: 'center' });
		row.querySelector<HTMLInputElement>('input[type="number"]')?.focus({ preventScroll: true });
	}

	/** Availability tag, label of the API: en stock, rupture de stock, plus commercialisée (part withdrawn). */
	function stock(part: SavPart): { label: string; tag: string } {
		if (!part.enabled) return { label: part.stock.label, tag: 'yack-tag--muted' };
		return { label: part.stock.label, tag: part.stock.inStock ? 'yack-tag--secondary' : 'yack-tag--tertiary' };
	}
</script>

<svelte:head>
	<title>{view.name} : {view.model.name} | Clik&amp;Yack</title>
</svelte:head>

<div class="container py-4 py-lg-5">
	<div class="mb-4"><Breadcrumbs items={data.breadcrumb} /></div>

	<div class="yack-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
		<div>
			<h1>{view.name}</h1>
			<p>{view.model.name} · ServiceCode <code>{view.model.serviceCode}</code></p>
		</div>
		{#if view.views.length > 1}
			<nav aria-label="Vues éclatées">
				<ul class="yack-chips">
					{#each view.views as other (other.id)}
						<li>
							<a
								class="yack-chip"
								class:is-active={other.id === view.id}
								href={savUrl(params.serial, view.model.serviceCode, other.id)}
								aria-current={other.id === view.id ? 'page' : undefined}>{other.name}</a
							>
						</li>
					{/each}
				</ul>
			</nav>
		{/if}
	</div>

	{#if !viewer.canSeePrices}
		<PricingNotice {viewer} />
	{/if}

	<div class="row g-4">
		<div class="col-xl-4">
			<div class="yack-exploded">
				<img src={view.imagePath} alt={view.name} />
				{#each view.items as item (item.marker)}
					<button
						type="button"
						class="yack-hotspot"
						class:is-active={active === item.marker}
						style:left="{item.x}%"
						style:top="{item.y}%"
						data-marker={item.marker}
						data-test-yack-hotspot={item.marker}
						aria-label="Repère {item.marker} : {item.part.name}"
						onmouseenter={() => (active = item.marker)}
						onmouseleave={() => (active = null)}
						onfocus={() => (active = item.marker)}
						onblur={() => (active = null)}
						onclick={() => focusRow(item.marker)}>{item.marker}</button
					>
				{/each}
			</div>
		</div>

		<div class="col-xl-8">
			<div class="yack-panel yack-panel--tight table-responsive">
				<table class="table yack-lines align-middle" data-test-yack-parts>
					<thead>
						<tr>
							<th scope="col">Rep.</th>
							<th scope="col">Pièce</th>
							<th scope="col" class="text-center">Qté</th>
							{#if viewer.canSeePrices}<th scope="col" class="text-end">Prix HT</th>{/if}
							<th scope="col">Dispo.</th>
							{#if viewer.canOrder}<th scope="col"><span class="visually-hidden">Panier</span></th>{/if}
						</tr>
					</thead>
					<tbody>
						{#each view.items as item (item.marker)}
							{@const part = item.part}
							{@const availability = stock(part)}
							<tr
								class:is-active={active === item.marker}
								data-marker={item.marker}
								data-test-yack-part-row={item.marker}
								onmouseenter={() => (active = item.marker)}
								onmouseleave={() => (active = null)}
								bind:this={rows[item.marker]}
							>
								<td><span class="yack-marker">{item.marker}</span></td>
								<td>
									<a href="/produit/{part.slug}" class="yack-line__name">{part.name}</a>
									<div class="yack-line__meta text-nowrap">Réf. {part.code}</div>
								</td>
								<td class="text-center">{item.quantity}</td>
								{#if viewer.canSeePrices}
									<td class="text-end text-nowrap" data-test-yack-part-price>{#if part.price !== null && part.enabled}{formatPrice(part.price)}{:else}<span class="small text-body-secondary">Non disponible</span>{/if}</td>
								{/if}
								<td class="text-nowrap"><span class="yack-tag {availability.tag}" data-test-yack-part-stock={part.stock.level}>{availability.label}</span></td>
								{#if viewer.canOrder}
									<td class="text-end">
										{#if part.enabled && part.variantCode && part.stock.inStock}
											<!-- One `addToCart` instance per part (shared with the product page, updates the header count). -->
											{@const cart = addToCart.for(part.variantCode)}
											{@const error = cart.fields.allIssues()?.[0]?.message}
											<form {...cart.enhance(async ({ submit }) => void (await submit()))} class="d-flex gap-1 justify-content-end">
												<input {...cart.fields.variant.as('hidden', part.variantCode)} />
												<input {...cart.fields.quantity.as('number', 1)} min="1" max="99" required class="form-control yack-part-qty" aria-label="Quantité de {part.name}" />
												<button class="btn btn-sm btn-primary text-nowrap" type="submit" disabled={cart.pending > 0} data-test-yack-add-part={item.marker}>Ajouter</button>
											</form>
											{#if error}
												<div class="small text-danger mt-1" role="alert">{error}</div>
											{:else if cart.result}
												{@const added = cart.result.added}
												<div class="small text-success mt-1" role="status" data-test-added-to-cart>
													{added > 1 ? `${added} ajoutées` : 'Ajoutée'} au panier · <a href="/panier">Voir le panier</a>
												</div>
											{/if}
										{/if}
									</td>
								{/if}
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
