<script lang="ts">
	// CMS pages of the builder: creation (createPage → editor) and list.
	import BuilderTopbar from '#lib/admin/BuilderTopbar.svelte';
	import { createPage, getPages } from '#lib/remote/builder.remote.js';

	const pages = $derived(await getPages());

	let slugTouched = $state(false);
	const slugify = (value: string) =>
		value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

	const error = $derived(createPage.fields.allIssues()?.[0]?.message);
</script>

<svelte:head><title>Pages CMS | Builder Clik&amp;Yack</title></svelte:head>

<BuilderTopbar current="pages" />

<main class="wrap">
	<section class="card">
		<h2>Nouvelle page</h2>
		{#if error}<p class="yb-error" role="alert">{error}</p>{/if}
		<form {...createPage} class="create">
			<label class="yb-field">
				Titre
				<input
					{...createPage.fields.title.as('text')}
					required
					placeholder="Offre printemps PAC"
					oninput={(event) => {
						if (!slugTouched) createPage.fields.slug.set(slugify(event.currentTarget.value));
					}}
				/>
			</label>
			<label class="yb-field">
				Adresse (slug)
				<input {...createPage.fields.slug.as('text')} oninput={() => (slugTouched = true)} required pattern="[a-z0-9]+(-[a-z0-9]+)*" />
			</label>
			<button class="yb-btn yb-btn-primary" type="submit" disabled={createPage.pending > 0}>Créer et ouvrir le builder</button>
		</form>
	</section>

	<section class="card">
		<h2>Pages</h2>
		<table>
			<thead><tr><th>Titre</th><th>Adresse</th><th>Statut</th><th>Modifiée le</th><th></th></tr></thead>
			<tbody>
				{#each pages as page (page.slug)}
					<tr>
						<td><strong>{page.title}</strong></td>
						<td><code>/{page.slug}</code></td>
						<td><span class="yb-badge yb-badge-{page.status}">{page.status === 'published' ? 'Publiée' : 'Brouillon'}</span></td>
						<td>{new Date(page.updatedAt.replace(' ', 'T')).toLocaleString('fr-FR')}</td>
						<td class="actions">
							<a class="yb-btn yb-btn-primary" href="/cms-admin/builder/{page.slug}">Éditer</a>
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
		border: 1px solid #e3e3e6;
		padding: 1.5rem;
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
