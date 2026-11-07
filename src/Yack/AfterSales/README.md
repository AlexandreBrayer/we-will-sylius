# Contexte AfterSales : SAV par numéro de série, vues éclatées, pièces

## Rôle

Retrouver un équipement installé à partir de ce que le technicien lit sur la plaque (numéro de série ou ServiceCode), afficher sa garantie, ses modèles, leurs vues éclatées et les pièces détachées correspondantes. L'ajout d'une pièce au panier passe par l'API panier standard de Sylius.

- Entrée : `Application/SerialLookup` (saisie vers modèles et garantie, résultat `Application/SerialLookupResult`).
- Saisie tolérante : `Domain/SerialNumberFormat` (casse, espaces, tirets).
- Garantie : `Domain/Warranty`.
- Entités Doctrine : `src/Entity/AfterSales/` (`Model`, `ExplodedView`, `ExplodedViewItem`, `SerialNumber`).

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `AfterSalesCatalog` | `Doctrine/DoctrineAfterSalesCatalog` | Parc installé et documentation technique (numéros de série, modèles, vues éclatées) |
| `WarrantyProvider` | `Warranty/FakeWarrantyProvider` | Dates de garantie (simulées) |

Autres adapters : `Api/AfterSalesProvider` (fournisseur API Platform des trois ressources), `Fixture/YackAfterSalesFixture` (modèles, vues éclatées, repères, numéros de série de démo ; s'exécute après la fixture du catalogue).

## Interfaces exposées aux autres contextes

Aucune. Lectures faites dans d'autres contextes :

- `Account\Domain\CommercePermission` (donnée) pour n'afficher les prix des pièces qu'aux clients autorisés ;
- `Catalog\Infrastructure\Sylius\VariantStock` pour le stock des pièces : **écart connu à l'ADR 008** (import de l'`Infrastructure` d'un autre contexte), résorbé par le ticket L0-01 (niveau de stock exposé par l'`Application` de Catalog).

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/yack/sav/serials/{n° de série ou ServiceCode}` | Modèles et garantie, 404 si inconnu |
| GET | `/api/v2/shop/yack/sav/models/{serviceCode}` | Un modèle et ses vues éclatées |
| GET | `/api/v2/shop/yack/sav/exploded-views/{id}` | Dessin SVG, repères et pièces ; prix des pièces pour ECOM seulement, `null` sinon |

Contrat détaillé : `docs/API_FRONT.md` §7. Numéros de série de démonstration : `docs/POC_YACK.md`. Front : route `sav`.

## Ce qui est simulé

- `FakeWarrantyProvider` : dates choisies à la main pour les numéros de démo, dates déterministes dérivées du numéro pour les autres. L'adapter réel appellera l'API garantie de Yack, même interface.
- Vues éclatées SVG dessinées pour la démo.
