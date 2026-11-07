# ADR 0001 : boutique headless SvelteKit, Sylius en back-office et API

- Statut : accepté (POC, Alexandre, novembre 2026), consigné a posteriori
- Contexte : refonte de l'e-shop B2B Clik&Yack

## Contexte

La boutique Twig de Sylius est difficile à habiller finement et mélange rendu et règles métier. Yack veut une boutique rapide, une charte stricte et des pages de contenu éditables sans développeur.

## Décision

- Sylius garde le back-office, l'API, les règles métier (prix, droits de commande, escompte, workflows) et les e-mails.
- La boutique et le builder CMS sont une application SvelteKit (`front/`) en BFF : elle appelle l'API côté serveur, garde les JWT en cookies `httpOnly`, rend en SSR.
- nginx route `/admin`, `/api`, `/media`, `/build` vers PHP et le reste vers le front. Les anciennes URL Twig répondent en 301.

## Conséquences

- Les règles ne sont écrites qu'une fois (Sylius). Le front ne fait qu'afficher ce que l'API autorise.
- Deux runtimes à exploiter (PHP-FPM et Node) : le déploiement doit les prévoir.
- Les composants du front sont en Svelte. Tout outil d'édition qui impose React (Puck, Builder.io) obligerait à réécrire le rendu des pages : voir ADR 0002.
