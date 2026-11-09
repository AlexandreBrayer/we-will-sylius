<script lang="ts">
	// Port of sav/index.html.twig with a result: warranty card (serial numbers only) and associated models.
	// Unknown serial number: the next steps (support with the number prefilled, the photo of the plate on /sav).
	import Icon from '#lib/layout/Icon.svelte';
	import SavSearch from '#lib/sav/SavSearch.svelte';
	import { getSavSerial } from '#lib/remote/sav.remote.js';
	import { NAMEPLATE_ANCHOR, contactUrl } from '#lib/sav/nameplate.js';
	import { savUrl } from '#lib/sav/types.js';

	let { params } = $props();
	const data = $derived(await getSavSerial(params.serial));

	/** "2019-06-03" → "03/06/2019". */
	const date = (iso: string) => iso.slice(0, 10).split('-').reverse().join('/');
	const plural = (count: number, word: string) => `${word}${count > 1 ? 's' : ''}`;
</script>

<svelte:head>
	<title>SAV : {data.query} | Clik&amp;Yack</title>
</svelte:head>

<SavSearch query={data.query} breadcrumb={data.breadcrumb}>
	{#if !data.result}
		<div class="yack-callout yack-callout--pending" data-test-yack-sav-not-found>
			<span class="yack-callout__title">Aucun appareil trouvé pour « {data.query} »</span>
			Vérifiez le numéro de série (plaque signalétique), essayez un des numéros de démonstration, ou :
			<a class="d-block mt-2" href="/sav#{NAMEPLATE_ANCHOR}" data-test-yack-sav-not-found-photo>Retrouver l'appareil par une photo de la plaque</a>
			<a class="d-block mt-1" href={contactUrl(data.query)} data-test-yack-sav-not-found-contact>Contacter le support Clik&amp;Yack</a>
		</div>
	{:else}
		{@const result = data.result}
		{#if result.warranty}
			{@const warranty = result.warranty}
			<section class="yack-sav-card mb-4" data-test-yack-warranty>
				<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
					<h2 class="yack-sav-card__title mb-0">Garantie : {warranty.serialNumber}</h2>
					<span class="yack-tag yack-tag--muted" title="Source des données de garantie">{warranty.source}</span>
				</div>
				<dl class="yack-facts">
					<dt>Mise en service</dt>
					<dd>{date(warranty.commissionedAt)}</dd>
					<dt>Pièces (2 ans)</dt>
					<dd class="d-flex flex-wrap gap-2 align-items-center" data-test-yack-warranty-parts={warranty.partsCovered ? 'covered' : 'expired'}>
						jusqu'au {date(warranty.partsCoverageEnd)}
						<span class="yack-tag {warranty.partsCovered ? 'yack-tag--secondary' : 'yack-tag--muted'}">{warranty.partsCovered ? 'sous garantie' : 'hors garantie'}</span>
					</dd>
					<dt>Compresseur (5 ans)</dt>
					<dd class="d-flex flex-wrap gap-2 align-items-center" data-test-yack-warranty-compressor={warranty.compressorCovered ? 'covered' : 'expired'}>
						jusqu'au {date(warranty.compressorCoverageEnd)}
						<span class="yack-tag {warranty.compressorCovered ? 'yack-tag--secondary' : 'yack-tag--muted'}">{warranty.compressorCovered ? 'sous garantie' : 'hors garantie'}</span>
					</dd>
				</dl>
			</section>
		{/if}

		<h2 class="yack-section-title mt-0">{result.models.length} {plural(result.models.length, 'modèle')} {plural(result.models.length, 'associé')}</h2>
		<div class="d-grid gap-2" data-test-yack-sav-models>
			{#each result.models as model (model.serviceCode)}
				<a class="yack-sav-link" href={savUrl(data.query, model.serviceCode)}>
					<span>
						<strong>{model.name}</strong>
						<small>{model.family} · ServiceCode <code>{model.serviceCode}</code></small>
					</span>
					<span class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
						<span class="yack-tag yack-tag--primary text-nowrap">{model.explodedViewCount} {plural(model.explodedViewCount, 'vue')} · {model.partCount} pièces</span>
						<Icon name="chevron-right" />
					</span>
				</a>
			{/each}
		</div>
	{/if}
</SavSearch>
