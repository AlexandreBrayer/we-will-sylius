<script lang="ts">
	// SAV home (port of sav/index.html.twig): serial number search in a pill on the left, demo numbers in
	// chips, `below` under it (/sav: « La pièce par une photo »), `children` (result) on the right (_sav.scss).
	import type { Snippet } from 'svelte';
	import { goto } from '$app/navigation';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import Icon from '#lib/layout/Icon.svelte';
	import type { Breadcrumb } from '#lib/types.js';
	import { DEMO_SERIALS, savUrl } from './types.js';

	let { query = '', breadcrumb, below, children }: { query?: string; breadcrumb: Breadcrumb[]; below?: Snippet; children?: Snippet } = $props();

	// With JavaScript: straight to /sav/{serial}. The GET form to /sav?serial= (redirected by getSavHome) stays for
	// no-JS; on the client it went through the cached remote query and its redirect, which ended in an error page
	// when the same number was searched again.
	function search(event: SubmitEvent & { currentTarget: HTMLFormElement }) {
		const serial = String(new FormData(event.currentTarget).get('serial') ?? '').trim();
		if (!serial) return;
		event.preventDefault();
		goto(savUrl(serial));
	}
</script>

<div class="container py-4 py-lg-5">
	<div class="mb-4"><Breadcrumbs items={breadcrumb} /></div>

	<div class="row g-4 g-lg-5">
		<div class="col-lg-5">
			<div class="yack-sav-card">
				<h1 class="mb-3">SAV &amp; pièces détachées</h1>
				<p class="text-body-secondary">
					Saisissez le numéro de série figurant sur la plaque signalétique de l'appareil (ou un code service) pour retrouver ses modèles, vues éclatées et pièces d'origine.
				</p>
				<form method="get" action="/sav" class="mb-4" role="search" onsubmit={search} data-test-yack-sav-search>
					<label class="form-label fw-bold" for="yack-serial">Numéro de série</label>
					<div class="yack-pill-search">
						<span class="yack-pill-search__icon"><Icon name="search" /></span>
						<input id="yack-serial" type="search" name="serial" value={query} class="form-control" placeholder="ex. YK25EVO08M001234" autocomplete="off" required />
						<button class="btn btn-primary" type="submit">Rechercher</button>
					</div>
				</form>
				<p class="small fw-bold mb-2">Numéros de démonstration</p>
				<ul class="yack-chips yack-chips--mono">
					{#each DEMO_SERIALS as demo (demo)}
						<li><a class="yack-chip" class:is-active={demo === query} href={savUrl(demo)}>{demo}</a></li>
					{/each}
				</ul>
			</div>
			{#if below}<div class="mt-4">{@render below()}</div>{/if}
		</div>

		<div class="col-lg-7">
			{@render children?.()}
		</div>
	</div>
</div>
