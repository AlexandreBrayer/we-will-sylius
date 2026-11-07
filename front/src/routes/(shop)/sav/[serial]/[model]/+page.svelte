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
	<title>{model.name} : SAV | Clik&amp;Yack</title>
</svelte:head>

<div class="container py-4 py-lg-5">
	<div class="mb-4"><Breadcrumbs items={data.breadcrumb} /></div>

	<div class="yack-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
		<div>
			<h1>{model.name}</h1>
			<p>{model.family} · ServiceCode <code>{model.serviceCode}</code></p>
		</div>
		{#if model.finishedProduct}
			<a class="btn btn-secondary" href="/produit/{model.finishedProduct.slug}">Fiche produit</a>
		{/if}
	</div>

	<div class="row g-4" data-test-yack-exploded-views>
		{#each model.explodedViews as view (view.id)}
			<div class="col-md-6">
				<a class="yack-sav-view h-100" href={savUrl(params.serial, model.serviceCode, view.id)}>
					<img src={view.imagePath} alt={view.name} />
					<span class="yack-sav-view__body">
						{view.name}
						<span class="yack-tag yack-tag--primary text-nowrap">{view.partCount} pièces</span>
					</span>
				</a>
			</div>
		{/each}
	</div>
</div>
