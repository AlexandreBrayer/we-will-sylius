<script lang="ts">
	// Template editor: free blocks + bound blocks of its type (mandatory one locked), canvas rendered
	// as the shop page of a sample product / category (shop header, breadcrumb, footer, product zones)
	// seen as the profile picked in "Voir comme"; settings (name, default template), assignments
	// (saved at once).
	import { page as current } from '$app/state';
	import type { YackContext } from '#lib/blocks/context.js';
	import TemplateBreadcrumb from '#lib/blocks/TemplateBreadcrumb.svelte';
	import type { CmsTemplate, PageBlock, PageStatus, TemplateAssignments } from '#lib/blocks/types.js';
	import BuilderShell from '#lib/editor/BuilderShell.svelte';
	import CatalogPicker from '#lib/editor/CatalogPicker.svelte';
	import { editorLoaders } from '#lib/editor/loaders.js';
	import ProfileStrip from '#lib/layout/ProfileStrip.svelte';
	import ShopFooter from '#lib/layout/ShopFooter.svelte';
	import ShopHeader from '#lib/layout/ShopHeader.svelte';
	import TabBar from '#lib/layout/TabBar.svelte';
	import { DEMO_PROFILES } from '#lib/layout/demo-profiles.js';
	import { assistanceEnabled } from '#lib/remote/assistance.remote.js';
	import { getTemplate, getTemplateAssignments, getTemplateSample, saveAssignments, saveTemplate } from '#lib/remote/builder.remote.js';
	import { getPublishedPages } from '#lib/remote/cms.remote.js';
	import { PREVIEW_VIEWERS, type Profile } from '#lib/types.js';

	let { params } = $props();

	const id = $derived(Number(params.id));
	const loaded = $derived(await getTemplate(id));
	// Edited settings; the editor reads the blocks once.
	// svelte-ignore state_referenced_locally
	let template = $state<CmsTemplate>(structuredClone(loaded));
	$effect.pre(() => {
		if (loaded.id !== template.id) template = structuredClone(loaded);
	});
	let dirty = $state(false);

	// Secondary (Affectations tab): not awaited, the editor shows without it.
	const assignmentsQuery = $derived(getTemplateAssignments(id));
	const assignments = $derived(assignmentsQuery.current);
	let assignError = $state<string | null>(null);

	/** "Voir comme": profile the canvas renders for (ECOM: prices and stock, header cart). */
	let profile = $state<Profile>('visitor');
	const viewer = $derived(PREVIEW_VIEWERS[profile]);
	/** Profiles of the switch, named as the demo bar of the shop names them. */
	const VIEW_AS = (['visitor', 'pro', 'ecom'] as const).map((value) => {
		const info = DEMO_PROFILES.find((entry) => entry.current(PREVIEW_VIEWERS[value]));
		return { value, label: info?.label ?? PREVIEW_VIEWERS[value].profileLabel, title: info?.can ?? '' };
	});

	/** Sample picked in the panel (null: first assigned product / category, else a catalog default). */
	let sampleCode = $state<string | null>(null);
	const sample = $derived(await getTemplateSample({ id, type: loaded.type, code: sampleCode, as: profile }));
	// Shop chrome of the canvas: same data as the root layout.
	const pages = $derived(await getPublishedPages());
	const assistance = $derived(await assistanceEnabled());
	const canvasCart = $derived(viewer.canOrder ? { itemsCount: 0, itemsTotal: 0 } : null);
	// Inert chrome: the cart link does not open the mini-cart.
	const noCart = (event: MouseEvent) => event.preventDefault();

	const isProduct = $derived(loaded.type === 'product');
	const typeLabel = $derived(isProduct ? 'Template de fiche produit' : 'Template de page catégorie');

	// Canvas context: getters, the blocks follow the sample and the profile.
	const context: YackContext = {
		get viewer() {
			return viewer;
		},
		catalog: null,
		editor: true,
		loaders: editorLoaders(() => profile),
		get product() {
			return sample?.product ?? null;
		},
		get taxon() {
			return sample?.taxon ?? null;
		}
	};

	const sampleName = $derived(sample?.product?.product.name ?? sample?.taxon?.taxon.name ?? null);

	function loginOnExpiry(e: unknown): string {
		const failure = e as { status?: number; body?: { message?: string }; message?: string };
		if (failure.status === 401) window.location.href = `/cms-admin/login?redirect=${encodeURIComponent(current.url.pathname)}`;
		return failure.body?.message ?? failure.message ?? 'Enregistrement impossible.';
	}

	async function save(status: PageStatus, collected: { blocks: PageBlock[]; grapesData: Record<string, unknown> }): Promise<string> {
		const saved = await saveTemplate({ id, name: template.name, isDefault: template.isDefault, status, ...collected });
		template.status = saved.status;
		template.isDefault = saved.isDefault;
		template.updatedAt = saved.updatedAt;
		return status === 'published' ? 'Template publié : les pages qui l’utilisent sont à jour.' : 'Brouillon enregistré (les pages gardent la version publiée).';
	}

	type Codes = Pick<TemplateAssignments, 'productCodes' | 'taxonCodes'>;
	async function assign(update: (codes: Codes) => Partial<Codes>) {
		if (!assignments) return;
		assignError = null;
		const { productCodes, taxonCodes } = assignments;
		try {
			await saveAssignments({ id, productCodes, taxonCodes, ...update({ productCodes, taxonCodes }) });
		} catch (e) {
			assignError = loginOnExpiry(e);
		}
	}

	const addProduct = (code: string) => assign(({ productCodes }) => ({ productCodes: [...productCodes, code] }));
	const removeProduct = (code: string) => assign(({ productCodes }) => ({ productCodes: productCodes.filter((c) => c !== code) }));
	const addTaxon = (code: string) => assign(({ taxonCodes }) => ({ taxonCodes: [...taxonCodes, code] }));
	const removeTaxon = (code: string) => assign(({ taxonCodes }) => ({ taxonCodes: taxonCodes.filter((c) => c !== code) }));
</script>

{#snippet viewAs()}
	<div class="view-as" role="group" aria-label="Voir comme">
		<span>Voir comme</span>
		{#each VIEW_AS as option (option.value)}
			<button class:active={profile === option.value} aria-pressed={profile === option.value} title={option.title} onclick={() => (profile = option.value)} data-test-view-as={option.value}
				>{option.label}</button
			>
		{/each}
	</div>
{/snippet}

<!-- Shop chrome of the canvas (inert): as the root layout renders it around the page. -->
{#snippet canvasTop()}
	<ShopHeader {viewer} cart={canvasCart} published={pages.map((p) => p.slug)} {assistance} onOpenCart={noCart} />
	<ProfileStrip {viewer} />
	<TemplateBreadcrumb items={sample?.product?.breadcrumb ?? sample?.taxon?.breadcrumb ?? []} />
{/snippet}

{#snippet canvasBottom()}
	<ShopFooter pages={pages.filter((p) => p.slug !== 'accueil')} />
	<TabBar {viewer} cart={canvasCart} onOpenCart={noCart} />
{/snippet}

{#snippet settings()}
	<h2>Template</h2>
	<label class="yb-field">Nom <input bind:value={template.name} oninput={() => (dirty = true)} data-test-template-name /></label>
	<label class="check">
		<input
			type="checkbox"
			bind:checked={template.isDefault}
			disabled={loaded.isDefault}
			onchange={() => (dirty = true)}
			data-test-template-default
		/>
		Template par défaut des {isProduct ? 'fiches produit' : 'pages catégorie'}
	</label>
	<p class="hint">
		{#if loaded.isDefault}
			Utilisé quand ni le produit ni sa catégorie n'ont de template. Pour en changer, cochez la case sur un autre template publié.
		{:else}
			Remplace l'actuel template par défaut à la publication (un template par défaut doit être publié).
		{/if}
	</p>

	<h2>Aperçu avec</h2>
	{#if sampleName}
		<p class="sample">
			<strong data-test-sample>{sampleName}</strong>
			<code>{sample?.code}</code>
		</p>
	{:else}
		<p class="hint">Aucun {isProduct ? 'produit' : 'catégorie'} d'exemple.</p>
	{/if}
	<CatalogPicker
		kind={isProduct ? 'product' : 'taxon'}
		label={isProduct ? 'Autre produit' : 'Autre catégorie'}
		placeholder={isProduct ? 'Nom ou code produit' : 'Nom de catégorie'}
		exclude={sample ? [sample.code] : []}
		onpick={(item) => (sampleCode = item.code)}
	/>
	<p class="hint">« Voir comme » choisit le profil du canvas (ECOM : prix et stock, sans panier). « Voir » ouvre la vraie page avec ce template (brouillon compris).</p>
{/snippet}

{#snippet assignmentList(items: { code: string; name: string }[], remove: (code: string) => void, empty: string)}
	<ul class="assigned">
		{#each items as item (item.code)}
			<li data-test-assigned={item.code}>
				<span>{item.name} <code>{item.code}</code></span>
				<button type="button" onclick={() => remove(item.code)} disabled={saveAssignments.pending > 0} aria-label="Retirer {item.name}">×</button>
			</li>
		{:else}
			<li class="none">{empty}</li>
		{/each}
	</ul>
{/snippet}

{#snippet assignmentsPanel()}
	{#if assignError}<p class="yb-error" role="alert">{assignError}</p>{/if}
	{#if !assignments}
		<p class="hint">Chargement des affectations…</p>
	{:else}
		{#if isProduct}
			<h2>Produits</h2>
			<p class="hint">La fiche de ces produits utilise ce template, quelle que soit leur catégorie.</p>
			{@render assignmentList(assignments.products, removeProduct, 'Aucun produit.')}
			<CatalogPicker kind="product" label="Ajouter un produit" placeholder="Nom ou code produit" exclude={assignments.productCodes} onpick={(item) => addProduct(item.code)} />
			<h2>Catégories — pages produits</h2>
			<p class="hint">Les fiches des produits de ces catégories (catégorie principale, sous-catégories comprises) utilisent ce template. La page catégorie elle-même dépend des templates de page catégorie.</p>
		{:else}
			<h2>Catégories — page catégorie</h2>
			<p class="hint">La page de ces catégories et de leurs sous-catégories utilise ce template. Les fiches produit de ces catégories dépendent des templates de fiche produit.</p>
		{/if}
		{@render assignmentList(assignments.taxons, removeTaxon, 'Aucune catégorie.')}
		<CatalogPicker kind="taxon" label="Ajouter une catégorie" placeholder="Nom de catégorie" exclude={assignments.taxonCodes} onpick={(item) => addTaxon(item.code)} />
		<p class="hint">Un produit ou une catégorie n'a qu'un template : l'ajouter ici le retire de son template précédent. Enregistré immédiatement.</p>
	{/if}
{/snippet}

{#key loaded.id}
	<BuilderShell
		title={template.name}
		subtitle={typeLabel}
		status={template.status}
		statusLabel={(template.status === 'published' ? 'Publié' : 'Brouillon') + (template.isDefault ? ' · par défaut' : '')}
		back={{ href: '/cms-admin/builder/templates', label: 'Tous les templates' }}
		view={sample ? { href: `${sample.url}?apercu=${id}`, label: 'Voir' } : null}
		content={loaded}
		templateType={loaded.type}
		{context}
		chrome={{ top: canvasTop, bottom: canvasBottom }}
		tools={viewAs}
		panels={[
			{ id: 'template', label: 'Template', body: settings },
			{ id: 'assignments', label: 'Affectations', body: assignmentsPanel }
		]}
		onsave={save}
		bind:dirty
	/>
{/key}

<style>
	.view-as {
		display: flex;
		align-items: center;
		border: 1px solid rgba(255, 255, 255, 0.3);
		border-radius: 0.375rem;
		overflow: hidden;
		font-size: 0.8rem;
	}
	.view-as span {
		padding: 0 0.6rem;
		opacity: 0.75;
	}
	.view-as button {
		background: transparent;
		color: #fff;
		border: 0;
		padding: 0.4rem 0.7rem;
		font-size: 0.8rem;
		cursor: pointer;
	}
	.view-as button.active {
		background: var(--yack-magenta);
	}
	.check {
		display: flex;
		gap: 0.5rem;
		align-items: center;
		font-size: 0.85rem;
		font-weight: 600;
		margin-bottom: 0.4rem;
	}
	.sample {
		display: flex;
		flex-direction: column;
		margin: 0 0 0.75rem;
		font-size: 0.85rem;
	}
	.sample code,
	.assigned code {
		font-size: 0.7rem;
		color: var(--yack-gray);
	}
	.assigned {
		list-style: none;
		margin: 0 0 0.75rem;
		padding: 0;
	}
	.assigned li {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.5rem;
		padding: 0.35rem 0;
		border-bottom: 1px solid var(--yack-border);
		font-size: 0.85rem;
	}
	.assigned li.none {
		color: var(--yack-gray);
	}
	.assigned button {
		border: 0;
		background: none;
		font-size: 1.1rem;
		line-height: 1;
		cursor: pointer;
		color: var(--yack-gray);
	}
	.assigned button:hover {
		color: var(--yack-magenta);
	}
</style>
