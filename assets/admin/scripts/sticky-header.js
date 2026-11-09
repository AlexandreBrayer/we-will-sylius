/*
 * Bandeau de titre collant : Sylius lui ajoute la classe « is-sticky » (fond, filet) quand il est collé en haut de la page.
 * Sa détection suppose un bandeau collé à 0 px ; sur grand écran la barre du haut de Yack est fixe (--yack-sticky-offset, voir
 * assets/admin/styles/_layout.scss) et le bandeau se colle dessous. Même principe que celui de Sylius, décalé de cette hauteur.
 */
const header = document.querySelector('[data-sticky-header]');

if (header) {
    let observer = null;

    const watch = () => {
        observer?.disconnect();

        const offset = Number.parseInt(getComputedStyle(document.documentElement).getPropertyValue('--yack-sticky-offset'), 10) || 0;

        observer = new IntersectionObserver(
            ([entry]) => entry.target.classList.toggle('is-sticky', entry.intersectionRatio < 1),
            { rootMargin: `-${offset}px 0px 0px 0px`, threshold: [1] },
        );
        observer.observe(header);
    };

    watch();
    // Le décalage change au seuil où Tabler replie le menu (1 200 px, $admin-desktop).
    window.matchMedia('(min-width: 1200px)').addEventListener('change', watch);
}
