<script lang="ts">
	// Port of installer/index.html.twig: filters sector / range / activity (applied on change), list and Leaflet map.
	// The filters live in the URL (shareable, SSR on direct load): a change navigates to `?secteur=&gamme=&activite=`
	// (GET form, works without JS) and only `getInstallers` re-runs.
	import { page } from '$app/state';
	import InstallerMap from '#lib/installers/InstallerMap.svelte';
	import { installerSearch } from '#lib/installers/types.js';
	import { getInstallerFilters, getInstallers } from '#lib/remote/installers.remote.js';

	const filters = $derived(await getInstallerFilters());
	const data = $derived(await getInstallers(installerSearch(page.url)));
	const criteria = $derived(data.criteria);

	const submit = (event: Event) => (event.currentTarget as HTMLSelectElement).form?.requestSubmit();
</script>

<svelte:head>
	<title>Trouver un installateur | Clik&amp;Yack</title>
	<meta name="description" content="Le réseau des installateurs partenaires Yack : installation, entretien et dépannage de vos équipements." />
</svelte:head>

<div class="container py-5">
	<h1 class="mb-2">Trouver un installateur</h1>
	<p class="text-body-secondary mb-4">Le réseau des installateurs partenaires Yack : installation, entretien et dépannage de vos équipements.</p>

	<form method="get" action="/trouver-un-installateur" class="row g-3 align-items-end mb-4" data-sveltekit-keepfocus data-sveltekit-noscroll data-test-yack-installer-filters>
		<div class="col-sm-6 col-md-3">
			<label class="form-label" for="secteur">Secteur</label>
			<select class="form-select" id="secteur" name="secteur" onchange={submit}>
				<option value="">Tous les secteurs</option>
				{#each filters.sectors as sector (sector)}
					<option value={sector} selected={criteria.sector === sector}>{sector}</option>
				{/each}
			</select>
		</div>
		<div class="col-sm-6 col-md-3">
			<label class="form-label" for="gamme">Gamme</label>
			<select class="form-select" id="gamme" name="gamme" onchange={submit}>
				<option value="">Toutes les gammes</option>
				{#each filters.ranges as range (range)}
					<option value={range} selected={criteria.range === range}>{range}</option>
				{/each}
			</select>
		</div>
		<div class="col-sm-6 col-md-3">
			<label class="form-label" for="activite">Activité</label>
			<select class="form-select" id="activite" name="activite" onchange={submit}>
				<option value="">Toutes les activités</option>
				{#each filters.activities as activity (activity.code)}
					<option value={activity.code} selected={criteria.activity === activity.code}>{activity.label}</option>
				{/each}
			</select>
		</div>
		<div class="col-sm-6 col-md-3 d-flex gap-2">
			<button class="btn btn-primary" type="submit">Filtrer</button>
			<a class="btn btn-link" href="/trouver-un-installateur">Réinitialiser</a>
		</div>
	</form>

	<div class="row g-4">
		<div class="col-lg-5 order-lg-2">
			<InstallerMap installers={data.installers} />
		</div>
		<div class="col-lg-7">
			<p class="fw-bold" data-test-yack-installer-count={data.count}>{data.count} installateur{data.count > 1 ? 's' : ''}</p>
			<div class="list-group">
				{#each data.installers as installer (installer.name)}
					<div class="list-group-item py-3" data-test-yack-installer>
						<div class="d-flex justify-content-between align-items-start gap-2">
							<div>
								<div class="fw-bold">{installer.name}</div>
								<div class="small text-body-secondary">{installer.postcode} {installer.city} · {installer.sector}</div>
							</div>
							{#if installer.certifiedRge}<span class="badge text-bg-success">RGE</span>{/if}
						</div>
						<div class="mt-2 d-flex flex-wrap gap-1">
							{#each installer.ranges as range (range)}<span class="badge text-bg-light border">{range}</span>{/each}
							{#each installer.activities as activity (activity.code)}<span class="badge text-bg-primary">{activity.label}</span>{/each}
						</div>
						{#if installer.phone}<div class="small mt-2">Tél. <a class="link-reset" href="tel:{installer.phone.replace(/\s/g, '')}">{installer.phone}</a></div>{/if}
					</div>
				{:else}
					<div class="alert alert-light">Aucun installateur ne correspond à ces critères.</div>
				{/each}
			</div>
		</div>
	</div>
</div>
