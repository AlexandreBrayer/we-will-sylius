<script lang="ts">
	// Port of sav/model.html.twig: exploded views of a model.
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import { getSavModel } from '#lib/remote/sav.remote.js';
	import { savUrl } from '#lib/sav/types.js';

	let { params } = $props();
	const data = $derived(await getSavModel({ serial: params.serial, model: params.model }));
	const model = $derived(data.model);
</script>

<svelte:head>
	<title>{model.name} — SAV | Clik&amp;Yack</title>
</svelte:head>

<div class="container py-5">
	<div class="mb-4"><Breadcrumbs items={data.breadcrumb} /></div>

	<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
		<div>
			<h1 class="mb-1">{model.name}</h1>
			<div class="text-body-secondary">{model.family} · ServiceCode <code>{model.serviceCode}</code></div>
		</div>
		{#if model.finishedProduct}
			<a class="btn btn-outline-secondary" href="/produit/{model.finishedProduct.slug}">Fiche produit</a>
		{/if}
	</div>

	<div class="row g-4" data-test-yack-exploded-views>
		{#each model.explodedViews as view (view.id)}
			<div class="col-md-6">
				<a class="card h-100 text-decoration-none" href={savUrl(params.serial, model.serviceCode, view.id)}>
					<img class="card-img-top bg-light" src={view.imagePath} alt={view.name} />
					<div class="card-body d-flex justify-content-between align-items-center gap-2">
						<span class="fw-bold">{view.name}</span>
						<span class="badge text-bg-primary rounded-pill text-nowrap">{view.partCount} pièces</span>
					</div>
				</a>
			</div>
		{/each}
	</div>
</div>
