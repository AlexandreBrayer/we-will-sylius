<script lang="ts">
	// Shop footer of the target design (demo "Demain" mode): four columns (brand and contact,
	// who we are, products, services). Only routes of the front and published CMS pages.
	import type { CmsPageSummary } from '#lib/blocks/types.js';

	/** Published CMS pages (the homepage "accueil" excluded). */
	let { pages }: { pages: CmsPageSummary[] } = $props();

	/** "Qui sommes-nous ?" column of the maquette: these CMS pages, while published. */
	const ABOUT = [
		{ slug: 'le-groupe', label: 'Yack' },
		{ slug: 'nos-services', label: 'Nos services' }
	];
	const about = $derived(ABOUT.filter((entry) => pages.some((page) => page.slug === entry.slug)));
</script>

<footer class="yack-footer mt-auto">
	<div class="container">
		<div class="yack-footer__grid">
			<div class="yack-footer__brand">
				<a href="/" class="d-inline-block" aria-label="Yack, accueil">
					<img src="/yack/logo-yack.png" alt="Yack, Clik&amp;Yack" class="yack-logo" width="1146" height="1031" />
				</a>
				<span class="yack-footer__title">Nous contacter</span>
				<a class="yack-link" href="/contact">Une question ? Écrivez-nous</a>
			</div>
			<nav aria-label="Pages Clik&amp;Yack" class="yack-footer__col">
				<span class="yack-footer__title">Qui sommes-nous ?</span>
				{#each about as entry (entry.slug)}
					<a href="/{entry.slug}">{entry.label}</a>
				{/each}
				<a href="/contact">Une question ?</a>
			</nav>
			<nav aria-label="Nos produits" class="yack-footer__col">
				<span class="yack-footer__title">Nos produits</span>
				<a href="/produits/pompes-a-chaleur/air-air">Pompe à chaleur air/air</a>
				<a href="/produits/pompes-a-chaleur/air-eau">Pompe à chaleur air/eau</a>
				<a href="/produits/eau-chaude-sanitaire">Eau chaude sanitaire</a>
				<a href="/produits/climatisation">Climatisation</a>
				<a href="/pieces">Pièces détachées</a>
			</nav>
			<nav aria-label="Services" class="yack-footer__col">
				<span class="yack-footer__title">Services</span>
				<a href="/sav">SAV &amp; pièces détachées</a>
				<a href="/trouver-un-installateur">Trouver un installateur</a>
				<a href="/compte/commandes">Suivre mes commandes</a>
			</nav>
		</div>
		<div class="yack-footer__bottom">
			&copy; Yack, Clik&amp;Yack (POC), propulsé par
			<a href="https://sylius.com" target="_blank" rel="noopener" class="text-decoration-underline">Sylius</a>
		</div>
	</div>
</footer>
