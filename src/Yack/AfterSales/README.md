# Contexte AfterSales : SAV par numéro de série, vues éclatées, pièces

## Rôle

Retrouver un équipement installé à partir de ce que le technicien lit sur la plaque (numéro de série ou ServiceCode), afficher sa garantie, ses modèles, leurs vues éclatées et les pièces détachées correspondantes. L'ajout d'une pièce au panier passe par l'API panier standard de Sylius.

- Entrée : `Application/SerialLookup` (saisie vers modèles et garantie, résultat `Application/SerialLookupResult`).
- Photo de la plaque ou de l'étiquette (« La pièce par une photo ») : `Application/IdentifyApplianceFromPhoto`. La photo (`Domain/NameplatePhoto` : JPEG, PNG ou WebP, 20 Mo, type lu sur le contenu ; HEIC refusé avec son propre message) est lue par `Domain/NameplateReader` (`Domain/NameplateReading` : numéro de série, référence du modèle, désignation). Numéro de série du parc installé : même recherche que la saisie manuelle. Sinon, référence présente au catalogue : fiche produit et noms de ses documents. Résultat `Application/ApplianceIdentification` : trouvé, produit du catalogue, inconnu, illisible, indisponible. La photo n'est jamais conservée.
- Saisie tolérante : `Domain/SerialNumberFormat` (casse, espaces, tirets).
- Garantie : `Domain/Warranty`.
- Entités Doctrine : `src/Entity/AfterSales/` (`Model`, `ExplodedView`, `ExplodedViewItem`, `SerialNumber`).

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `AfterSalesCatalog` | `Doctrine/DoctrineAfterSalesCatalog` | Parc installé et documentation technique (numéros de série, modèles, vues éclatées) |
| `WarrantyProvider` | `Warranty/FakeWarrantyProvider` | Dates de garantie (simulées) |
| `NameplateReader` | `Nameplate/NameplateReaderSelector` (alias) : `Nameplate/FakeNameplateReader` par défaut, `Nameplate/LanguageModelNameplateReader` si `YACK_AI_ADAPTER` vaut `anthropic` ou `mistral` | Lecture du numéro de série, de la référence et de la désignation sur la photo |

Autres adapters : `Api/AfterSalesProvider` (fournisseur API Platform des trois ressources), `Api/NameplateReadingProcessor` et `Api/NameplateReadingView` (photo de la plaque), `Fixture/YackAfterSalesFixture` (modèles, vues éclatées, repères, numéros de série de démo ; s'exécute après la fixture du catalogue).

## Interfaces exposées aux autres contextes

| Interface (`Application/`) | Implémentation | Rôle | Lue par |
|---|---|---|---|
| `ApplianceModelsInterface` | `Application/ApplianceModels` | N° de série ou ServiceCode vers les codes des produits finis de ses modèles (null si l'appareil est absent du parc), lecture seule de `AfterSalesCatalog::findModels` | Assistance (manuels des modèles d'un n° de série) |

Lectures faites dans d'autres contextes :

- `Account\Domain\CommercePermission` (donnée) pour n'afficher les prix des pièces qu'aux clients autorisés ;
- `Catalog\Application\VariantStockReaderInterface` pour le stock des pièces (ADR 008) ;
- `Catalog\Application\ProductReferenceReaderInterface` pour la référence lue sur une étiquette (donnée `Catalog\Domain\ProductReference`).

Utilise `Common` : `LanguageModel` (adapter réel, plafond quotidien) et `ExifStripper` (photo réencodée sans EXIF, 1 600 px au plus, avant tout envoi).

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/yack/sav/serials/{n° de série ou ServiceCode}` | Modèles et garantie, 404 si inconnu |
| GET | `/api/v2/shop/yack/sav/models/{serviceCode}` | Un modèle et ses vues éclatées |
| GET | `/api/v2/shop/yack/sav/exploded-views/{id}` | Dessin SVG, repères et pièces ; prix des pièces pour ECOM seulement, `null` sinon |
| POST | `/api/v2/shop/yack/sav/nameplate-readings` | Photo (multipart, champ `photo`) vers `{status, serialNumber, reference, model, product, simulated}` ; 422 sur `photo`, 503 si la lecture est indisponible |

Contrat détaillé : `docs/API_FRONT.md` §7. Numéros de série de démonstration : `docs/POC_YACK.md`. Front : route `sav`.

## Ce qui est simulé

- `FakeWarrantyProvider` : dates choisies à la main pour les numéros de démo, dates déterministes dérivées du numéro pour les autres. L'adapter réel appellera l'API garantie de Yack, même interface.
- Vues éclatées SVG dessinées pour la démo.
- `FakeNameplateReader` : ne lit pas l'image ; il reconnaît les plaques FICTIF de `public/yack/demo/plaques/` (générées par `bin/yack-demo-plaques.php`) par le bloc texte PNG « YackDemoNameplate » qu'elles portent. Toute autre photo est illisible. Réponse `simulated: true`.
