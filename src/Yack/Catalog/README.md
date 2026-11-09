# Contexte Catalog : catalogue maître Yack et synchronisation PIM/ERP

## Rôle

Yack est maître de son catalogue (produits finis et pièces détachées, prix, stocks, attributs techniques). Ce contexte recopie ses lots dans le catalogue Sylius du canal Yack : création, mise à jour ou désactivation de chaque produit, un enregistrement invalide étant journalisé et ignoré sans bloquer le reste du lot.

- Message `Application/SynchronizeCatalog` et `Application/SynchronizeCatalogHandler` (Messenger, synchrone aujourd'hui ; passer en asynchrone est une affaire de configuration).
- Modèle : `Domain/SourceProduct` (produit tel que publié par Yack), `Domain/ProductKind` (`finished`, `spare_part`), `Domain/ProductFeed`, `Domain/RejectedRecord`, `Domain/SyncReport`, `Domain/SyncOutcome`.
- Disponibilité : `Domain/StockLevel`, binaire pour le client : « En stock » (`en_stock`, commandable) ou « Rupture de stock » (`rupture`, ni panier ni commande) ; une pièce détachée désactivée garde, dans les vues éclatées du SAV, son propre niveau « Plus commercialisée » (`plus_commercialisee`). Tous les produits suivent les règles du profil (visiteur et PRO sans prix, ECOM prix et panier).
- Exceptions : `Domain/InvalidSourceProduct` (enregistrement inapplicable), `Domain/ProductSourceUnavailable`.

Le contexte porte aussi la **recherche de la boutique** (page `/recherche` et suggestions de l'en-tête) :

- `Application/SearchCatalog` : cas d'usage ; le moteur configuré (`YACK_SEARCH_ENGINE` : `meilisearch` ou `database`, `Domain/SearchEngine`) répond, et si Meilisearch ne répond pas (`Domain/SearchUnavailable` : injoignable, délai d'une seconde dépassé, erreur, index absent), la base répond à sa place et le repli est journalisé (`var/log/yack_search.log`, canal `yack_search`). Une recherche n'échoue jamais à cause du moteur.
- `Application/SearchIndexer` (interface `Application/SearchIndexerInterface`) : reconstruction complète de l'index et mise à jour des produits appliqués par chaque synchronisation du catalogue (`SynchronizeCatalogHandler`, après l'écriture) ; cette mise à jour ne tourne qu'avec `YACK_SEARCH_ENGINE=meilisearch` et n'échoue jamais (un moteur absent est journalisé, la reconstruction suivante rattrape).
- Modèle : `Domain/SearchQuery` (texte normalisé, page, nature), `Domain/SearchResult` (codes par pertinence et total, jamais de prix), `Domain/Suggestions` (`Domain/ProductSuggestion`, `Domain/CategorySuggestion`), `Domain/SearchDocument` et `Domain/CategoryDocument` (ce que l'index connaît), `Domain/SearchVocabulary` (synonymes métier : clim, PAC, ECS, DRV/VRF/VRV, UE, UI, ballon, régulation…).

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `ProductSource` | `Source/JsonFileProductSource` | Lit un export JSON au format de l'API PIM/ERP de Yack (`data/yack/*.json`) : champs, caractéristiques (`specs`) et attributs (gamme, modèle, typologie, secteurs, documentation), photo ou visuel de repli ; un champ sans correspondance est ignoré |
| `ProductCatalog` | `Sylius/SyliusProductCatalog` | Écrit les produits dans le catalogue Sylius (toutes les références résolues avant de toucher l'entité) |
| `ProductSearch` | `Meilisearch/MeilisearchProductSearch` | Recherche Meilisearch : pertinence, fautes de frappe, synonymes, référence cherchée par son début ; tous les mots exigés, les derniers rendus facultatifs si aucun produit ne les a tous |
| `ProductSearch` | `Doctrine/DoctrineProductSearch` | Recherche en base (moteur `database` et repli) : chaque mot, ou un synonyme, dans le nom français, le code, la marque, la gamme, la référence constructeur, la typologie ou le modèle |
| `ProductSearchIndex` | `Meilisearch/MeilisearchProductIndex` | Index `products` et `categories` : réglages, documents, attente de chaque tâche |
| `SearchDocumentSource` | `Doctrine/DoctrineSearchDocumentSource` | Produits du canal Yack et catégories des branches Clik&Yack et Clik&Yack Parts, lus dans Sylius en français |

Index Meilisearch (`Meilisearch/MeilisearchIndexes`) : identifiant = code ; champs cherchables par priorité décroissante `code`, `manufacturer_reference`, `name`, `brand`, `range`, `typology` (produit fini ou pièce détachée), `category_path`, `short_description` ; filtrables `kind`, `enabled`, `category_codes`, `brand` ; aucune faute tolérée sur le code et la référence constructeur ; un mot tapé comme une référence (lettres et chiffres : `SRK25`, `FDC224`) est d'abord cherché dans ces deux champs seulement, car les noms citent aussi des références (`SRK25` trouve les dix `SRK25…`, jamais `SRK35ZS-W`), puis dans tous les textes s'il n'y est pas ; l'attribut où le mot est trouvé passe avant l'exactitude, donc une correspondance sur la référence arrive en tête. **Aucun prix dans l'index** : la boutique lit les cartes produit dans l'API catalogue, qui masque les prix selon le profil.

Autres adapters : `Console/SyncCatalogCommand` (`app:yack:catalog:sync`, cible `make yack-sync`), `Console/ReindexSearchCommand` (`app:yack:search:reindex`, cibles `make yack-search-reindex` et `make yack-search-up`), `Fixture/YackCatalogFixture` (le catalogue de démo passe par le même pipeline que la synchro), `Api/YackProductNormalizer` (bloc `yack` des produits de l'API shop : nature, marque, gamme, attributs, stock et son niveau), `Api/ProductTypeFilterExtension` (filtre `?yack_type=product|part`).

## Interfaces exposées aux autres contextes

Lues par d'autres contextes (ADR 008) :

- `Application/VariantStockReaderInterface` (implémentée par `Application/VariantStockReader`) : stock et niveau de stock d'une variante, lu par AfterSales pour les pièces ;
- `Application/ProductKindResolverInterface` (implémentée par `Application/ProductKindResolver`) : produit fini ou pièce détachée, lu par Payment pour les lignes de commande.
- `Application/ProductReferenceReaderInterface` (implémentée par `Application/ProductReferenceReader`) : produit actif désigné par une référence lue sur une étiquette (casse, tirets et espaces tolérés, `Domain/ProductReference::candidates()`), avec son slug, sa marque et les noms de ses documents ; lu par AfterSales pour la photo d'une étiquette et par Assistance pour la fiche du catalogue d'une référence (manuels et notices).

## Ressources API

Le contexte enrichit les produits natifs de l'API shop de Sylius :

| Méthode | Route | Apport Yack |
|---|---|---|
| GET | `/api/v2/shop/products` | Filtre `yack_type=product` (produits finis) ou `yack_type=part` (pièces), bloc `yack` |
| GET | `/api/v2/shop/products/{code}` | Bloc `yack` (prix sur la variante, masqués par Account pour visiteur et PRO) |

Et expose ses propres ressources (`Infrastructure/Api`) :

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/v2/shop/yack/catalog-facets` | Facettes d'une liste (marque, gamme, puissance, fluide) et leurs effectifs |
| GET | `/api/v2/shop/yack/search?q=&limit=&page=&type=&category=` | Codes des produits trouvés, par pertinence, et leur total (`SearchView`, `SearchProvider`) |
| GET | `/api/v2/shop/yack/search/suggest?q=` | 6 produits et 3 catégories proposés pendant la saisie (`SuggestionsView`) |

Contrat détaillé : `docs/API_FRONT.md` §4. Front : routes `produits`, `pieces`, `produit`, `recherche`, composant `front/src/lib/layout/SearchSuggest.svelte`.

## Ce qui est simulé

- `JsonFileProductSource` lit `data/yack/catalog.json` (catalogue initial) et `data/yack/api_feed_update.json` (lot delta de `make yack-sync`) au lieu de l'API PIM/ERP. Brancher la vraie API = un nouvel adapter de `ProductSource` (client HTTP, même mapping).
- Produits finis : références, libellés, descriptions, catégories, marques et photos du catalogue public yack.fr ; prix, stocks et EAN fictifs (formules de la maquette). Les photos ne sont pas versionnées : `data/yack/images/reel/telecharger.sh` les télécharge, et l'import prend le visuel de repli (`fallback_image`) d'une photo absente.
- Pièces détachées et leurs gammes (Evolys, Kaze) fictives : le parcours SAV s'en sert. Chaque pièce porte la marque de l'appareil qu'elle sert (MHI ou Yack).
- Champs de l'export lus par personne : `family` (autres versions d'un même modèle, base d'une future association Sylius « même famille »), `ean`, `source_id`.
- `documents` : noms des documents de yack.fr (notices, documentation produit), recopiés dans l'attribut texte `documentation` ; les fichiers eux-mêmes ne sont pas dans le catalogue de la démo.
- Recherche : `YACK_SEARCH_ENGINE=database` par défaut (`.env`), la boutique tourne sans Meilisearch. Activer Meilisearch : `YACK_SEARCH_ENGINE=meilisearch` dans `.env.local`, puis `make yack-search-up` (service `meilisearch` de `compose.yml`, profil `search`, et construction des index).
