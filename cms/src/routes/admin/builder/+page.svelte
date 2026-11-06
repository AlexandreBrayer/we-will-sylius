<script lang="ts">
	let { data, form } = $props();
	// svelte-ignore state_referenced_locally
	let title = $state(form?.title ?? '');
	// svelte-ignore state_referenced_locally
	let slug = $state(form?.slug ?? '');
	let slugTouched = $state(false);

	const slugify = (value: string) =>
		value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

	$effect(() => {
		if (!slugTouched) slug = slugify(title);
	});
</script>

<svelte:head><title>Pages CMS | Builder Clik&amp;Yack</title></svelte:head>

<header class="topbar">
	<img class="yb-logo" src="{data.syliusUrl}/yack/logo-yack.png" alt="Yack" />
	<strong>Builder de pages</strong>
	<span class="spacer"></span>
	<a class="yb-btn yb-btn-outline" href="{data.syliusUrl}/admin/yack/contenu/pages">Retour à l'admin Sylius</a>
	<form method="POST" action="/admin/logout"><button class="yb-btn yb-btn-outline">Déconnexion</button></form>
</header>

<main class="wrap">
	<section class="card">
		<h2>Nouvelle page</h2>
		{#if form?.error}<p class="yb-error" role="alert">{form.error}</p>{/if}
		<form method="POST" action="?/create" class="create">
			<label class="yb-field">Titre <input name="title" bind:value={title} required placeholder="Offre printemps PAC" /></label>
			<label class="yb-field">Adresse (slug) <input name="slug" bind:value={slug} oninput={() => (slugTouched = true)} required pattern="[a-z0-9]+(-[a-z0-9]+)*" /></label>
			<button class="yb-btn yb-btn-primary" type="submit">Créer et ouvrir le builder</button>
		</form>
	</section>

	<section class="card">
		<h2>Pages</h2>
		<table>
			<thead><tr><th>Titre</th><th>Adresse</th><th>Statut</th><th>Modifiée le</th><th></th></tr></thead>
			<tbody>
				{#each data.pages as page (page.slug)}
					<tr>
						<td><strong>{page.title}</strong></td>
						<td><code>/{page.slug}</code></td>
						<td><span class="yb-badge yb-badge-{page.status}">{page.status === 'published' ? 'Publiée' : 'Brouillon'}</span></td>
						<td>{new Date(page.updatedAt.replace(' ', 'T')).toLocaleString('fr-FR')}</td>
						<td class="actions">
							<a class="yb-btn yb-btn-primary" href="/admin/builder/{page.slug}">Éditer</a>
							<a class="yb-btn yb-btn-outline" href="/{page.slug}{page.status === 'published' ? '' : '?apercu=1'}" target="_blank">
								{page.status === 'published' ? 'Voir' : 'Aperçu'}
							</a>
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</section>
</main>

<style>
	.topbar {
		display: flex;
		align-items: center;
		gap: 1rem;
		padding: 0.6rem 1.5rem;
		background: var(--yack-dark);
		color: #fff;
	}
	.topbar .yb-logo {
		background: #fff;
		border-radius: 0.25rem;
		padding: 2px;
	}
	.spacer {
		flex: 1;
	}
	.topbar form {
		margin: 0;
	}
	.wrap {
		max-width: 1100px;
		margin: 2rem auto;
		padding: 0 1rem;
		display: grid;
		gap: 1.5rem;
	}
	.card {
		background: #fff;
		border-radius: 0.75rem;
		padding: 1.5rem;
		border-top: 4px solid var(--yack-magenta);
	}
	h2 {
		margin-top: 0;
		font-size: 1.15rem;
	}
	.create {
		display: grid;
		grid-template-columns: 1fr 1fr auto;
		gap: 1rem;
		align-items: end;
	}
	.create .yb-btn {
		margin-bottom: 0.9rem;
	}
	table {
		width: 100%;
		border-collapse: collapse;
	}
	th,
	td {
		padding: 0.6rem;
		border-bottom: 1px solid var(--yack-border);
		text-align: left;
		font-size: 0.9rem;
	}
	.actions {
		display: flex;
		gap: 0.5rem;
		justify-content: flex-end;
	}
</style>
