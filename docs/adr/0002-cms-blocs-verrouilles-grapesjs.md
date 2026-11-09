# ADR 0002 : pages CMS en blocs verrouillés, éditeur GrapesJS

- Statut : proposé (à valider par Thomas et Alexandre)
- Contexte : Yack veut éditer ses pages (accueil, landing pages, services) sans casser la charte. Trois éditeurs open source ou SaaS ont été comparés : GrapesJS, Puck, Builder.io.

## Décision

1. **La source de vérité d'une page est la liste JSON de blocs** (`CmsPage::blocks`), validée par `PageBlocks::normalize()` contre le catalogue `BlockType`. L'état de l'éditeur (`grapesData`) n'est qu'un confort d'édition.
2. **Éditeur : GrapesJS** (open source, BSD-3), sans gestionnaire de styles, sans bloc HTML, sans couleur libre : l'éditeur ne règle que des propriétés prévues.
3. **Chaque bloc peut cibler un profil** (`audience` : tous, visiteur, pro, ecom, connectés). Le filtrage se fait au rendu SSR, selon le profil renvoyé par l'API.

## Options écartées

| Option | Pourquoi pas |
|---|---|
| Puck | Éditeur React : le rendu de la boutique est en Svelte (ADR 0001). Il faudrait maintenir les blocs en double ou passer la boutique en React. |
| Builder.io | SaaS propriétaire, abonnement et dépendance à un tiers pour publier une page. Contraire à la règle PURJUS « pas de SaaS bloquant ». |
| HTML libre | Charte impossible à garantir, risque XSS. |

## Conséquences

- Comme le rendu ne dépend que du JSON de blocs, **l'éditeur est remplaçable** sans migration de contenu : changer d'éditeur = réécrire `front/src/lib/editor/`, pas les pages.
- Ajouter un bloc touche six fichiers (voir `AGENTS.md`), dont la validation serveur et ses tests.
- Le ciblage par profil est un affichage, pas une protection : un bloc réservé aux ECOM ne doit jamais contenir d'information confidentielle (prix, conditions), qui restent portées par l'API.
