# Contexte Catalog : catalogue maître Yack et synchronisation PIM/ERP

## Rôle

Yack est maître de son catalogue (produits finis et pièces détachées, prix, stocks, attributs techniques). Ce contexte recopie ses lots dans le catalogue Sylius du canal Yack : création, mise à jour ou désactivation de chaque produit, un enregistrement invalide étant journalisé et ignoré sans bloquer le reste du lot.

- Message `Application/SynchronizeCatalog` et `Application/SynchronizeCatalogHandler` (Messenger, synchrone aujourd'hui ; passer en asynchrone est une affaire de configuration).
- Modèle : `Domain/SourceProduct` (produit tel que publié par Yack), `Domain/ProductKind` (`finished`, `spare_part`), `Domain/ProductFeed`, `Domain/RejectedRecord`, `Domain/SyncReport`, `Domain/SyncOutcome`.
- Exceptions : `Domain/InvalidSourceProduct` (enregistrement inapplicable), `Domain/ProductSourceUnavailable`.

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `ProductSource` | `Source/JsonFileProductSource` | Lit un export JSON au format de l'API PIM/ERP de Yack (`data/yack/*.json`) |
| `ProductCatalog` | `Sylius/SyliusProductCatalog` | Écrit les produits dans le catalogue Sylius (toutes les références résolues avant de toucher l'entité) |

Autres adapters : `Console/SyncCatalogCommand` (`app:yack:catalog:sync`, cible `make yack-sync`), `Fixture/YackCatalogFixture` (le catalogue de démo passe par le même pipeline que la synchro), `Api/YackProductNormalizer` (bloc `yack` des produits de l'API shop : nature, marque, gamme, attributs, stock), `Api/ProductTypeFilterExtension` (filtre `?yack_type=product|part`), `Sylius/VariantStock` (stock affiché d'une variante).

## Interfaces exposées aux autres contextes

Aucune interface d'`Application` à ce jour. Deux autres contextes importent aujourd'hui son `Infrastructure`, **écarts connus à l'ADR 008** :

- AfterSales importe `Sylius/VariantStock` (résorbé par le ticket L0-01 : niveau de stock exposé par l'`Application` de Catalog) ;
- Payment importe `Api/YackProductNormalizer` (résorbé par le ticket L0-02 : type de produit exposé par une façade d'`Application`).

## Ressources API

Pas de ressource propre : le contexte enrichit les produits natifs de l'API shop de Sylius.

| Méthode | Route | Apport Yack |
|---|---|---|
| GET | `/api/v2/shop/products` | Filtre `yack_type=product` (produits finis) ou `yack_type=part` (pièces), bloc `yack` |
| GET | `/api/v2/shop/products/{code}` | Bloc `yack` (prix sur la variante, masqués par Account pour visiteur et PRO) |

Contrat détaillé : `docs/API_FRONT.md` §4. Front : routes `produits`, `pieces`, `produit`, `recherche`.

## Ce qui est simulé

- `JsonFileProductSource` lit `data/yack/catalog.json` (catalogue initial) et `data/yack/api_feed_update.json` (lot delta de `make yack-sync`) au lieu de l'API PIM/ERP. Brancher la vraie API = un nouvel adapter de `ProductSource` (client HTTP, même mapping).
- Produits, marques, prix, stocks et images fictifs.
