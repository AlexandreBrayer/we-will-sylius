<script lang="ts">
	// Port of sav/index.html.twig with a result: warranty card (serial numbers only) and associated models.
	import SavSearch from '#lib/sav/SavSearch.svelte';
	import { savUrl } from '#lib/sav/types.js';

	let { data } = $props();

	/** "2019-06-03" → "03/06/2019". */
	const date = (iso: string) => iso.slice(0, 10).split('-').reverse().join('/');
	const plural = (count: number, word: string) => `${word}${count > 1 ? 's' : ''}`;
</script>

<svelte:head>
	<title>SAV — {data.query} | Clik&amp;Yack</title>
</svelte:head>

<SavSearch query={data.query} breadcrumb={data.breadcrumb}>
	{#if !data.result}
		<div class="alert alert-warning" data-test-yack-sav-not-found>
			Aucun appareil trouvé pour « {data.query} ». Vérifiez le numéro de série (plaque signalétique) ou contactez le support technique Yack.
		</div>
	{:else}
		{@const result = data.result}
		{#if result.warranty}
			{@const warranty = result.warranty}
			<div class="card mb-4" data-test-yack-warranty>
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-start gap-2">
						<h2 class="h5 mb-3">Garantie — {warranty.serialNumber}</h2>
						<span class="badge text-bg-light" title="Source des données de garantie">{warranty.source}</span>
					</div>
					<dl class="row mb-0">
						<dt class="col-sm-5">Mise en service</dt>
						<dd class="col-sm-7">{date(warranty.commissionedAt)}</dd>
						<dt class="col-sm-5">Pièces (2 ans)</dt>
						<dd class="col-sm-7" data-test-yack-warranty-parts={warranty.partsCovered ? 'covered' : 'expired'}>
							jusqu'au {date(warranty.partsCoverageEnd)}
							<span class="badge {warranty.partsCovered ? 'text-bg-success' : 'text-bg-secondary'}">{warranty.partsCovered ? 'sous garantie' : 'hors garantie'}</span>
						</dd>
						<dt class="col-sm-5">Compresseur (5 ans)</dt>
						<dd class="col-sm-7" data-test-yack-warranty-compressor={warranty.compressorCovered ? 'covered' : 'expired'}>
							jusqu'au {date(warranty.compressorCoverageEnd)}
							<span class="badge {warranty.compressorCovered ? 'text-bg-success' : 'text-bg-secondary'}">{warranty.compressorCovered ? 'sous garantie' : 'hors garantie'}</span>
						</dd>
					</dl>
				</div>
			</div>
		{/if}

		<h2 class="h5 mb-3">{result.models.length} {plural(result.models.length, 'modèle')} {plural(result.models.length, 'associé')}</h2>
		<div class="list-group" data-test-yack-sav-models>
			{#each result.models as model (model.serviceCode)}
				<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3 py-3" href={savUrl(data.query, model.serviceCode)}>
					<span>
						<span class="fw-bold d-block">{model.name}</span>
						<span class="small text-body-secondary">{model.family} · ServiceCode <code>{model.serviceCode}</code></span>
					</span>
					<span class="badge text-bg-primary rounded-pill text-nowrap">
						{model.explodedViewCount} {plural(model.explodedViewCount, 'vue')} · {model.partCount} pièces
					</span>
				</a>
			{/each}
		</div>
	{/if}
</SavSearch>
