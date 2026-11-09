<script lang="ts">
	// Port of installer/index.html.twig: filters sector / range / activity in chips, numbered list and
	// Leaflet map with the same numbers (hovering a row or a pin highlights the other one).
	// The filters live in the URL (shareable, SSR on direct load): each chip is a link to
	// `?secteur=&gamme=&activite=` (works without JS) and only `getInstallers` re-runs.
	import { page } from '$app/state';
	import Icon from '#lib/layout/Icon.svelte';
	import InstallerMap from '#lib/installers/InstallerMap.svelte';
	import { installerSearch, type InstallerCriteria } from '#lib/installers/types.js';
	import { getInstallerFilters, getInstallers } from '#lib/remote/installers.remote.js';

	const filters = $derived(await getInstallerFilters());
	const data = $derived(await getInstallers(installerSearch(page.url)));
	const criteria = $derived(data.criteria);

	/** Installer highlighted in the list and on the map (index in `data.installers`). */
	let active = $state<number | null>(null);

	const PARAMS: Record<keyof InstallerCriteria, string> = { sector: 'secteur', range: 'gamme', activity: 'activite' };

	/** URL of the page with one criterion set to `value` (null: removed), the others kept. */
	function filterHref(key: keyof InstallerCriteria, value: string | null): string {
		const query = new URLSearchParams();
		for (const name of Object.keys(PARAMS) as (keyof InstallerCriteria)[]) {
			const current = name === key ? value : criteria[name];
			if (current) query.set(PARAMS[name], current);
		}
		const search = query.toString();
		return search ? `/trouver-un-installateur?${search}` : '/trouver-un-installateur';
	}

	const groups = $derived([
		{ key: 'sector' as const, legend: 'Secteur', all: 'Tous les secteurs', options: filters.sectors.map((value) => ({ value, label: value })) },
		{ key: 'range' as const, legend: 'Gamme', all: 'Toutes les gammes', options: filters.ranges.map((value) => ({ value, label: value })) },
		{ key: 'activity' as const, legend: 'Activité', all: 'Toutes les activités', options: filters.activities.map((a) => ({ value: a.code, label: a.label })) }
	]);
	const filtered = $derived(Boolean(criteria.sector || criteria.range || criteria.activity));
</script>

<svelte:head>
	<title>Trouver un installateur | Clik&amp;Yack</title>
	<meta name="description" content="Le réseau des installateurs partenaires Yack : installation, entretien et dépannage de vos équipements." />
</svelte:head>

<div class="container py-4 py-lg-5">
	<div class="yack-page-head">
		<h1>Trouver un installateur</h1>
		<p>Le réseau des installateurs partenaires Yack : installation, entretien et dépannage de vos équipements.</p>
	</div>

	<div class="yack-sav-card mb-4" data-sveltekit-keepfocus data-sveltekit-noscroll data-test-yack-installer-filters>
		<div class="row gx-lg-5">
			{#each groups as group (group.key)}
				{@const current = criteria[group.key]}
				<div class="col-lg-4">
					<fieldset class="yack-filter-group">
						<legend>{group.legend}</legend>
						<ul class="yack-chips">
							<li>
								<a class="yack-chip" class:is-active={!current} href={filterHref(group.key, null)} aria-current={!current ? 'true' : undefined}>{group.all}</a>
							</li>
							{#each group.options as option (option.value)}
								{@const selected = current === option.value}
								<li>
									<a
										class="yack-chip"
										class:is-active={selected}
										href={filterHref(group.key, selected ? null : option.value)}
										aria-current={selected ? 'true' : undefined}
										data-test-yack-installer-filter={`${PARAMS[group.key]}:${option.value}`}>{option.label}</a
									>
								</li>
							{/each}
						</ul>
					</fieldset>
				</div>
			{/each}
		</div>
		{#if filtered}
			<a class="yack-link" href="/trouver-un-installateur">Réinitialiser les filtres <Icon name="close" /></a>
		{/if}
	</div>

	<div class="row g-4">
		<div class="col-lg-5 order-lg-2">
			<InstallerMap installers={data.installers} bind:active />
		</div>
		<div class="col-lg-7">
			<p class="fw-bold" data-test-yack-installer-count={data.count}>{data.count} installateur{data.count > 1 ? 's' : ''}</p>
			{#if data.installers.length === 0}
				<div class="yack-callout yack-callout--pending">Aucun installateur ne correspond à ces critères. Retirez un filtre pour élargir la recherche.</div>
			{:else}
				<ol class="yack-installers">
					{#each data.installers as installer, index (installer.name)}
						<li
							class="yack-installer"
							class:is-active={active === index}
							data-test-yack-installer
							onmouseenter={() => (active = index)}
							onmouseleave={() => (active = null)}
							onfocusin={() => (active = index)}
							onfocusout={() => (active = null)}
						>
							<span class="yack-marker" aria-hidden="true">{index + 1}</span>
							<div class="yack-installer__body">
								<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
									<div>
										<div class="yack-installer__name">{installer.name}</div>
										<div class="small text-body-secondary">{installer.postcode} {installer.city} · {installer.sector}</div>
									</div>
									{#if installer.certifiedRge}<span class="yack-tag yack-tag--primary">RGE</span>{/if}
								</div>
								<div class="yack-installer__tags">
									{#each installer.ranges as range (range)}<span class="yack-tag yack-tag--muted">{range}</span>{/each}
									{#each installer.activities as activity (activity.code)}<span class="yack-tag yack-tag--secondary">{activity.label}</span>{/each}
								</div>
								{#if installer.phone}
									<div class="small mt-2">Tél. <a class="link-reset fw-bold" href="tel:{installer.phone.replace(/\s/g, '')}">{installer.phone}</a></div>
								{/if}
							</div>
						</li>
					{/each}
				</ol>
			{/if}
		</div>
	</div>
</div>
