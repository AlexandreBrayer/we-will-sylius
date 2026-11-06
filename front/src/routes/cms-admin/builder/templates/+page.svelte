<script lang="ts">
	// CMS templates of the builder (fiche produit, page catégorie): list by type with the default one,
	// creation (createTemplate → editor), duplication (duplicateTemplate → editor of the copy).
	import BuilderTopbar from '#lib/admin/BuilderTopbar.svelte';
	import type { TemplateType } from '#lib/blocks/types.js';
	import { createTemplate, duplicateTemplate, getTemplates } from '#lib/remote/builder.remote.js';

	const templates = $derived(await getTemplates());

	const TYPES: { type: TemplateType; label: string; hint: string }[] = [
		{ type: 'product', label: 'Fiches produit', hint: 'Appliqué selon : produit, sinon sa catégorie principale (ou un parent), sinon le template par défaut.' },
		{ type: 'taxon', label: 'Pages catégorie', hint: 'Appliqué selon : catégorie, sinon un parent, sinon le template par défaut.' }
	];

	const error = $derived(createTemplate.fields.allIssues()?.[0]?.message);
	const plural = (count: number, one: string, many: string) => `${count} ${count > 1 ? many : one}`;
</script>

<svelte:head><title>Templates | Builder Clik&amp;Yack</title></svelte:head>

<BuilderTopbar current="templates" />

<main class="wrap">
	<section class="card">
		<h2>Nouveau template</h2>
		{#if error}<p class="yb-error" role="alert">{error}</p>{/if}
		<form {...createTemplate} class="create">
			<label class="yb-field">
				Type
				<select {...createTemplate.fields.type.as('select')}>
					<option value="product">Fiche produit</option>
					<option value="taxon">Page catégorie</option>
				</select>
			</label>
			<label class="yb-field">Nom <input {...createTemplate.fields.name.as('text')} required placeholder="Ballons premium" /></label>
			<button class="yb-btn yb-btn-primary" type="submit" disabled={createTemplate.pending > 0}>Créer et ouvrir le builder</button>
		</form>
	</section>

	{#each TYPES as { type, label, hint } (type)}
		<section class="card" data-test-templates={type}>
			<h2>{label}</h2>
			<p class="hint">{hint}</p>
			<table>
				<thead><tr><th>Nom</th><th>Statut</th><th>Affecté à</th><th>Modifié le</th><th></th></tr></thead>
				<tbody>
					{#each templates.filter((t) => t.type === type) as template (template.id)}
						{@const duplicate = duplicateTemplate.for(String(template.id))}
						<tr data-test-template={template.name}>
							<td>
								<strong>{template.name}</strong>
								{#if template.isDefault}<span class="yb-badge yb-badge-default">Par défaut</span>{/if}
							</td>
							<td><span class="yb-badge yb-badge-{template.status}">{template.status === 'published' ? 'Publié' : 'Brouillon'}</span></td>
							<td class="muted">
								{#if template.assignments.products + template.assignments.taxons === 0}
									{template.isDefault ? 'tout le reste' : '—'}
								{:else}
									{[
										template.assignments.products ? plural(template.assignments.products, 'produit', 'produits') : '',
										template.assignments.taxons ? plural(template.assignments.taxons, 'catégorie', 'catégories') : ''
									]
										.filter(Boolean)
										.join(', ')}
								{/if}
							</td>
							<td>{new Date(template.updatedAt.replace(' ', 'T')).toLocaleString('fr-FR')}</td>
							<td class="actions">
								<a class="yb-btn yb-btn-primary" href="/cms-admin/builder/templates/{template.id}">Éditer</a>
								<form {...duplicate}>
									<input {...duplicate.fields.id.as('hidden', String(template.id))} />
									<button class="yb-btn yb-btn-outline" disabled={duplicate.pending > 0}>Dupliquer</button>
								</form>
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</section>
	{/each}
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
		padding: 1.5rem;
		border-top: 4px solid var(--yack-magenta);
	}
	h2 {
		margin-top: 0;
		font-size: 1.15rem;
	}
	.hint,
	.muted {
		color: var(--yack-gray);
		font-size: 0.85rem;
	}
	.create {
		display: grid;
		grid-template-columns: 220px 1fr auto;
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
	td strong {
		margin-right: 0.5rem;
	}
	.actions {
		display: flex;
		gap: 0.5rem;
		justify-content: flex-end;
	}
	.actions form {
		margin: 0;
	}
	.yb-badge-default {
		background: var(--yack-dark);
		color: #fff;
	}
</style>
