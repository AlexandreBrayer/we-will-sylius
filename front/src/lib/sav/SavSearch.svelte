<script lang="ts">
	// SAV home (port of sav/index.html.twig): serial number search on the left, `children` (result) on the right.
	import type { Snippet } from 'svelte';
	import Breadcrumbs from '#lib/catalog/Breadcrumbs.svelte';
	import type { Breadcrumb } from '#lib/types.js';
	import { DEMO_SERIALS, savUrl } from './types.js';

	let { query = '', breadcrumb, children }: { query?: string; breadcrumb: Breadcrumb[]; children?: Snippet } = $props();
</script>

<div class="container py-5">
	<div class="mb-4"><Breadcrumbs items={breadcrumb} /></div>

	<div class="row g-5">
		<div class="col-lg-5">
			<h1 class="mb-3">SAV &amp; pièces détachées</h1>
			<p class="text-body-secondary">
				Saisissez le numéro de série figurant sur la plaque signalétique de l'appareil (ou un code service) pour retrouver ses modèles, vues éclatées et pièces d'origine.
			</p>
			<form method="get" action="/sav" class="mb-3" data-test-yack-sav-search>
				<label class="form-label fw-bold" for="yack-serial">Numéro de série</label>
				<div class="input-group input-group-lg">
					<input id="yack-serial" type="search" name="serial" value={query} class="form-control" placeholder="ex. YK25EVO08M001234" autocomplete="off" required />
					<button class="btn btn-primary" type="submit">Rechercher</button>
				</div>
			</form>
			<details class="small text-body-secondary">
				<summary>Numéros de démonstration</summary>
				<ul class="mt-2 mb-0">
					{#each DEMO_SERIALS as demo (demo)}
						<li><a href={savUrl(demo)}>{demo}</a></li>
					{/each}
				</ul>
			</details>
		</div>

		<div class="col-lg-7">
			{@render children?.()}
		</div>
	</div>
</div>
