<script lang="ts">
	// Port of sav/exploded_view.html.twig (+ exploded-view Stimulus controller): numbered hotspots on the
	// drawing and parts table highlight each other on hover; price and "Ajouter" for ECOM accounts only.
	import { enhance } from '$app/forms';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import PricingNotice from '#lib/catalog/PricingNotice.svelte';
	import { formatPrice } from '#lib/format.js';
	import { getViewer } from '#lib/remote/session.remote.js';
	import { savUrl, type SavPart } from '#lib/sav/types.js';

	let { data, form } = $props();
	const view = $derived(data.view);
	const viewer = $derived(await getViewer());

	/** Marker hovered (drawing or table). */
	let active = $state<number | null>(null);
	let submitting = $state<number | null>(null);
	/** Table rows by marker. */
	const rows: Record<number, HTMLTableRowElement> = {};

	/** Click on a hotspot: bring its table row into view (and its quantity field into focus). */
	function focusRow(marker: number) {
		const row = rows[marker];
		if (!row) return;
		row.scrollIntoView({ behavior: 'smooth', block: 'center' });
		row.querySelector<HTMLInputElement>('input[name="quantity"]')?.focus({ preventScroll: true });
	}

	function stock(part: SavPart): { label: string; badge: string } {
		if (!part.enabled) return { label: part.stock.label, badge: 'text-bg-secondary' };
		if (part.stock.inStock) {
			return { label: part.stock.tracked ? `${part.stock.label} (${part.stock.available})` : part.stock.label, badge: 'text-bg-success' };
		}
		return { label: part.stock.label, badge: 'text-bg-warning' };
	}
</script>

<svelte:head>
	<title>{view.name} — {view.model.name} | Clik&amp;Yack</title>
</svelte:head>

<div class="container py-5">
	<div class="mb-4"><Breadcrumbs items={data.breadcrumb} /></div>

	<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
		<div>
			<h1 class="mb-1">{view.name}</h1>
			<div class="text-body-secondary">{view.model.name} · ServiceCode <code>{view.model.serviceCode}</code></div>
		</div>
		{#if view.views.length > 1}
			<div class="btn-group flex-wrap" role="group" aria-label="Vues éclatées">
				{#each view.views as other (other.id)}
					<a
						class="btn btn-sm {other.id === view.id ? 'btn-primary' : 'btn-outline-primary'}"
						href={savUrl(data.serial, view.model.serviceCode, other.id)}
						aria-current={other.id === view.id ? 'page' : undefined}>{other.name}</a
					>
				{/each}
			</div>
		{/if}
	</div>

	{#if !viewer.canSeePrices}
		<PricingNotice {viewer} />
	{/if}

	{#if form?.error && form.marker === null}
		<div class="alert alert-danger" role="alert">{form.error}</div>
	{:else if form?.added}
		<div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-3" role="status" data-test-added-to-cart>
			<span>{form.added > 1 ? `${form.added} × ` : ''}« {form.part} » {form.added > 1 ? 'ajoutées' : 'ajoutée'} au panier.</span>
			<a class="btn btn-sm btn-outline-success text-nowrap" href="/panier">Voir le panier</a>
		</div>
	{/if}

	<div class="row g-4">
		<div class="col-xl-5">
			<div class="yack-exploded position-sticky" style="top: 1rem">
				<img class="img-fluid w-100 rounded-3 border" src={view.imagePath} alt={view.name} />
				{#each view.items as item (item.marker)}
					<button
						type="button"
						class="yack-hotspot"
						class:is-active={active === item.marker}
						style="left: {item.x}%; top: {item.y}%"
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

		<div class="col-xl-7">
			<div class="table-responsive">
				<table class="table table-sm align-middle" data-test-yack-parts>
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
									<a href="/produit/{part.slug}" class="fw-bold text-decoration-none">{part.name}</a>
									<div class="small text-body-secondary">Réf. {part.code}</div>
								</td>
								<td class="text-center">{item.quantity}</td>
								{#if viewer.canSeePrices}
									<td class="text-end text-nowrap" data-test-yack-part-price>{part.price !== null && part.enabled ? formatPrice(part.price) : '—'}</td>
								{/if}
								<td class="text-nowrap"><span class="badge {availability.badge}">{availability.label}</span></td>
								{#if viewer.canOrder}
									<td class="text-end">
										{#if part.enabled && part.variantCode}
											<form
												method="POST"
												action="?/addToCart"
												class="d-flex gap-1 justify-content-end"
												use:enhance={() => {
													submitting = item.marker;
													return async ({ update }) => {
														await update({ reset: false });
														submitting = null;
													};
												}}
											>
												<input type="hidden" name="marker" value={item.marker} />
												<input type="number" name="quantity" value="1" min="1" max="99" required class="form-control form-control-sm" style="width: 3.75rem" aria-label="Quantité" />
												<button class="btn btn-sm btn-primary text-nowrap" type="submit" disabled={submitting === item.marker} data-test-yack-add-part={item.marker}>Ajouter</button>
											</form>
											{#if form?.error && form.marker === item.marker}
												<div class="small text-danger mt-1" role="alert">{form.error}</div>
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
