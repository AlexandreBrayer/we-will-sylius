# ADR 0006 : feuille de style de la boutique découpée en partiels

- Statut : proposé (à valider par Thomas et Alexandre)
- Contexte : `assets/shop/styles/yack.scss` faisait 729 lignes et portait tout l'habillage (Bootstrap, en-tête, accueil, SAV, tunnel, authentification, compte, blocs CMS). Le lot 1 fait travailler plusieurs agents en parallèle sur la charte : un fichier unique garantit des conflits de fusion à chaque paquet.

## Décision

1. **`yack.scss` ne contient que des `@use` de partiels**, dans l'ordre de l'ancienne feuille : l'ordre de la cascade est inchangé.
2. **Un partiel par zone fonctionnelle**, chacun commençant par `@use 'tokens' as *;` :

| Partiel | Contenu |
|---|---|
| `_base.scss` | Variables Bootstrap, boutons, champs, accents de marque |
| `_layout.scss` | En-tête, pied de page |
| `_components.scss` | Composants de base de la charte (à créer) |
| `_catalog.scss` | Accueil de repli (bandeau, tuiles), catalogue, fiche |
| `_sav.scss` | Vue éclatée, repères, carte |
| `_checkout.scss` | Progression, encadrés de paiement, RIB de Yack (virement) |
| `_auth.scss` | Connexion, inscription, choix du profil |
| `_account.scss` | Espace client |
| `_cms-blocks.scss` | Blocs CMS `.yack-cms-*` |

3. **Les tokens restent la source unique** (`_tokens.scss`) : aucun partiel ne déclare de couleur, de rayon ni de police en dur.
4. **Le découpage ne change pas le rendu** : la sortie compilée est identique, octet pour octet, à celle d'avant le découpage (contrôle par SHA-256 de la sortie de `sass`).

## Options écartées

| Option | Pourquoi pas |
|---|---|
| Garder un fichier unique | Conflits de fusion systématiques entre paquets parallèles. |
| `@import` | Déprécié par Sass, variables globales, CSS des tokens dupliqué à chaque import. |
| Styles dans les composants Svelte | Le canevas du builder et les gabarits Twig chargent la même feuille : les styles doivent rester dans la feuille compilée par Encore. |

## Conséquences

- Un module Sass n'émet son CSS qu'une fois : les `@font-face` de `_tokens.scss` restent en tête de la sortie, même si chaque partiel l'utilise.
- Ajouter un bloc CMS touche `_cms-blocks.scss` (et non plus `yack.scss`) dans la liste des six fichiers d'`AGENTS.md`.
- Un nouveau partiel s'ajoute à la liste des `@use` de `yack.scss`, à la place qui respecte la cascade.
