# Instructions pour agents de code (Claude, Cursor, Copilot…) : POC Yack

Règles de travail sur ce dépôt. Elles reprennent les conventions PURJUS (celles de rrg-api) adaptées à un projet Sylius headless. À lire avant d'écrire la moindre ligne.

## Le quoi

E-shop B2B Clik&Yack (génie climatique) : catalogue produits finis et pièces détachées, profils Visiteur / PRO / ECOM, SAV par numéro de série, annuaire d'installateurs, pages de contenu éditées par Yack.

- **Sylius 2.2 / Symfony 7.4** : back-office, API (`/api/v2`), règles métier, e-mails.
- **Front SvelteKit** (`front/`, Svelte 5, TypeScript strict) : boutique et builder CMS, en BFF (les JWT restent dans des cookies `httpOnly`).
- Carte complète, comptes de démo et script de démo : `docs/POC_YACK.md`. Contrat de l'API boutique : `docs/API_FRONT.md`.

## Stack

PHP 8.3 · Symfony 7.4 · Sylius 2.2 · API Platform · Doctrine ORM · MySQL · SvelteKit 3 / Svelte 5 · GrapesJS 0.23 · Bootstrap 5 (+ `assets/shop/styles/yack.scss`).

## Architecture

Monolithe modulaire. Le code Yack vit dans `src/Yack/<Contexte>/`, un dossier par contexte métier :

```
src/Yack/{Account, AfterSales, Catalog, Content, Installer, Payment}/
├── Domain/          # Règles métier, ports (interfaces), objets valeur, énumérations
├── Application/     # Cas d'usage (commandes, handlers Messenger, DTO d'entrée)
└── Infrastructure/  # Adapters : Api (API Platform), Persistence/Doctrine, Fixture, Mailer, Twig, Menu, Console…
```

- Les entités Doctrine surchargées de Sylius restent dans `src/Entity/<Contexte>/` (contrainte de Sylius pour les ressources).
- Le code doit être **supprimable par contexte** : retirer `src/Yack/Installer/` ne doit casser que l'annuaire.

### Règles

1. **Domain sans dépendance technique** autre que Symfony, Doctrine et API Platform (règle PURJUS). Pas d'appel HTTP, pas de SDK tiers dans `Domain/`.
2. **Ports dans `Domain/`, adapters dans `Infrastructure/`.** Exemple : `Catalog\Domain\ProductSource` ← `Catalog\Infrastructure\Source\JsonFileProductSource`. Brancher les vraies API Yack = écrire un nouvel adapter, rien d'autre.
3. **Un contexte n'importe pas l'`Infrastructure` d'un autre contexte.** Il passe par un port du `Domain` de l'autre contexte ou par un événement. Écarts connus à résorber : `AfterSales → Catalog\Infrastructure\Sylius\VariantStock` et `Payment → Catalog\Infrastructure\Api\YackProductNormalizer`.
4. **Classes `final` par défaut**, `declare(strict_types=1)` partout.
5. **Méthodes métier plutôt que setters** (`$request->accept()`, pas `setStatus('accepted')`).
6. **Attributs PHP 8** pour le mapping, la validation et la sérialisation.
7. **Exception au plus près de la mutation** (ex. `PageBlocks::normalize()` refuse un bloc hors charte avant l'écriture en base).
8. **Les règles métier restent dans Sylius.** Le front n'invente pas de règle : il affiche ce que l'API autorise (prix `null`, 403 panier pour un PRO).

## Pages CMS : la charte est verrouillée

Une page est une liste ordonnée de blocs `{type, props}`. Pas de HTML libre, pas de CSS libre, pas de couleur libre.

Ajouter ou modifier un bloc touche **toujours ces fichiers, dans le même commit** :

| Fichier | Rôle |
|---|---|
| `src/Yack/Content/Domain/BlockType.php` | Type, libellé, propriétés et valeurs autorisées (source de vérité, validée côté serveur) |
| `front/src/lib/blocks/types.ts` | Union `BlockType` du front |
| `front/src/lib/blocks/registry.ts` | Champs du builder (traits) et valeurs par défaut |
| `front/src/lib/blocks/components.ts` + `<Bloc>.svelte` | Rendu (pages publiques et canevas du builder) |
| `assets/shop/styles/yack.scss` | Styles `.yack-cms-*`, uniquement avec les tokens de `_tokens.scss` |
| `tests/Yack/Content/Domain/PageBlocksTest.php` | Cas valides et refus |

Chaque bloc accepte la propriété `audience` (tous, visiteur, pro, ecom, connectes) : le rendu public filtre selon le profil, le builder affiche tout.

## Charte graphique

Design system de la maquette de Quentin (Figma « YACK Seduction ») : tokens dans `assets/shop/styles/_tokens.scss`. Aucune couleur, aucun rayon, aucune police en dur dans les composants : on ajoute un token d'abord.

## Tests

- Structure miroir : `tests/Yack/<Contexte>/<Couche>/…Test.php` reflète `src/Yack/`.
- Format : `declare(strict_types=1)`, `@internal`, `final class`, `assertSame()` (jamais `assertEquals()`), mocks sur interfaces uniquement, `#[DataProvider]` statique qui retourne un `\Iterator`.
- Tout nouveau code de `Domain/` arrive avec ses tests unitaires.

## Commandes

```bash
make yack-reset && make yack-up                                   # stack complète sur http://localhost:8088
docker compose exec php vendor/bin/phpunit tests/Yack             # tests unitaires Yack
docker compose exec php vendor/bin/phpstan analyse src/Yack       # analyse statique
docker compose exec php vendor/bin/ecs check src/Yack             # style de code
docker compose exec front npm run check                           # svelte-check (0 erreur attendu)
```

## Git

- Branches : `[NUM_TICKET]_[description]` dès qu'il y a un ticket Podio, `poc/<sujet>` pendant la phase POC.
- Une merge request = un sujet relisible. Les décisions structurantes passent par un ADR (`docs/adr/`).
- Avant de pousser : tests Yack verts, `npm run check` à 0 erreur, PHPStan sans nouvelle erreur sur `src/Yack`.

## Anti-patterns

| À éviter | À faire |
|---|---|
| Logique métier dans un provider API Platform ou un composant Svelte | `Domain/` ou `Application/` |
| Nouveau bloc CMS déclaré seulement côté front | Les 6 fichiers du tableau ci-dessus |
| Couleur ou rayon en dur dans un `.svelte` ou le SCSS | Token dans `_tokens.scss` |
| Lien de bloc non contrôlé (`href={props.url}`) | `resolveLink()` (refuse `javascript:`, `data:`…) |
| Import de l'`Infrastructure` d'un autre contexte | Port dans le `Domain` du contexte cible |
| Nouveau service externe sans trace | ADR dans `docs/adr/` |
