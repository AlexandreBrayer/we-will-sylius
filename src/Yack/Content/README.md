# Contexte Content : pages CMS et templates des pages communes

## Rôle

Contenu édité par Yack dans le builder SvelteKit (`/cms-admin/builder`), sans casser la charte : une page ou un template est une liste ordonnée de blocs `{type, props}` choisis dans un catalogue fermé. Pas de HTML libre, pas de CSS libre, pas de couleur libre (ADR 0002).

- **Pages CMS** : entité `src/Entity/Content/CmsPage.php` (`slug`, `blocks` JSON = source du rendu, `grapesData` = état de l'éditeur, statut `Domain/CmsPageStatus` brouillon ou publié). Une page refuse tout bloc lié.
- **Templates des pages communes** (travail d'Alex) : entité `src/Entity/Content/CmsTemplate.php`, type `Domain/CmsTemplateType` (`product` pour `/produit/{slug}`, `taxon` pour les pages catégorie `/produits/…` et `/pieces/…`), un seul défaut publié par type.
- **Catalogue des blocs** : `Domain/BlockType`, miroir de `front/src/lib/blocks/registry.ts`.
  - Blocs libres (`scope()` nul), autorisés partout : `hero`, `texte-image`, `produits`, `categories`, `cta`, `faq`, `reassurance`, `chiffres-cles`, `acces-rapides`, `documents`. Eux seuls acceptent la propriété `audience` (tous, visiteur, pro, ecom, connectes) : le rendu public filtre selon le profil, le builder affiche tout.
  - Blocs liés au produit (template `product`) : `produit-galerie`, `produit-infos`, `produit-achat` (obligatoire, unique), `produit-caracteristiques`, `produit-description`, `produit-documents`, `produit-sav`, `produits-associes`.
  - Blocs liés à la catégorie (template `taxon`) : `categorie-entete`, `categorie-produits` (obligatoire, unique), `categorie-sous-categories`.
  - Un bloc lié n'a aucune propriété de donnée : il lit le produit ou la catégorie de la page rendue.
  - Conteneurs de mise en page (`isContainer()`), blocs libres : `section` (blocs rangés dans `children` : des `colonnes` ou des blocs de contenu) et `colonnes` (`columns` : une liste de blocs par colonne ; props `ratio` `50-50|33-66|66-33|33-33-33`, `alignement` `haut|centre|bas`, `espacement` = clé d'espacement de la charte). Deux niveaux au plus : racine > section > colonnes > colonne > bloc. Une colonne ne prend ni section, ni colonnes, ni bloc pleine largeur (`BlockType::FULL_WIDTH` : `hero`, `produits`, `categories`, `cta`, `chiffres-cles`, `tuiles`) ; une section ne prend pas de section ; un bloc lié reste à la racine d'un template. Style d'un conteneur : `BlockType::CONTAINER_STYLE` (fond, texte, espacements, largeur, masquage, ancre). Le stockage reste une liste (`{type, props, style}`) : une page sans conteneur est inchangée, sans migration.
- **Validation** : `Domain/PageBlocks` (bloc inconnu, propriété inconnue, valeur hors liste, bloc lié hors de son type, bloc obligatoire absent ou en double, conteneur mal placé ou mal formé : `Domain/InvalidPageBlocks`, réponse 422), récursive. Chaque message nomme le bloc par son chemin (`Domain/BlockPath` : « Bloc n°2 (FAQ) » à la racine, « Section 2 › colonnes 1 › colonne 2 › bloc 1 (FAQ) » dans un conteneur). Au plus 30 blocs par page, conteneurs et blocs imbriqués compris (`PageBlocks::count()`). Les lectures de l'arbre (`PageBlocks::walk()`, `map()`, `deviations()`, `images()`) servent au rapport des écarts de charte, à « Remplacer partout », à l'historique, aux sections enregistrées et à la médiathèque (une image utilisée dans une colonne n'est pas supprimable).
- **Résolution** d'un template : `Application/CmsTemplateResolver`, templates publiés seulement (un template affecté mais en brouillon est ignoré). Produit : template du produit, sinon template produits de la catégorie principale puis de ses ancêtres, sinon défaut `product`. Catégorie : template de la catégorie, sinon de ses ancêtres, sinon défaut `taxon`. Résultat `Domain/ResolvedCmsTemplate`, origine `Domain/CmsTemplateSource` (`product`, `taxon`, `default`).
- **Affectations** : `Product::$cmsTemplate`, `Taxon::$cmsTemplate` (page catégorie) et `Taxon::$productCmsTemplate` (fiches produit de la catégorie), même donnée dans le back-office, l'API et le builder ; refus `Domain/InvalidCmsTemplateAssignment`.

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `CmsPages` | `Persistence/DoctrineCmsPages` | Pages (publiées, toutes) |
| `CmsTemplates` | `Persistence/DoctrineCmsTemplates` | Templates (par identifiant, défaut, publiés par type) |
| `CmsTemplateAssignments` | `Persistence/DoctrineCmsTemplateAssignments` | Produits et catégories affectés à un template |

Autres adapters :

- API : `Api/PublishedCmsPageProvider` (la boutique ne voit que les pages publiées), `Api/CmsTemplateProcessor` (un défaut par type, 409 à la suppression du défaut), `Api/CmsTemplateDuplicateProcessor`, `Api/CmsTemplateAssignmentsProvider` et `Processor`, `Api/ResolvedCmsTemplateProvider` ;
- back-office (style hors périmètre) : `Controller/Admin/CmsPageAdminController` et `CmsTemplateAdminController` (*Contenu → Pages, Templates*, liens vers le builder), `Menu/AdminMenuListener`, champ « Template CMS » des formulaires produit et taxon (`Form/ProductTypeExtension`, `Form/TaxonTypeExtension`, `Form/CmsTemplateChoices`, hooks de `config/packages/yack.yaml`), `Twig/CmsExtension` (liens vers le front, template hérité) ;
- fixtures : `Fixture/YackCmsPageFixture` et `Fixture/YackCmsTemplateFixture` (`config/yack/fixtures_cms.yaml`, blocs passés par `PageBlocks`, affectations par `CmsTemplateAssignments`).

## Interfaces exposées aux autres contextes

Aucune. Le contexte lit les produits et catégories de Sylius (`src/Entity/Product`, `src/Entity/Taxonomy`) mais aucun autre contexte Yack.

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/cms-pages` | Pages publiées |
| GET | `/api/v2/shop/cms-pages/{slug}` | Une page publiée (404 pour un brouillon) |
| GET | `/api/v2/shop/cms-templates/resolve?type=product\|taxon&code={code}` | Template effectif d'une fiche produit ou d'une page catégorie (public) |
| GET, POST | `/api/v2/admin/cms-pages` | Liste, création (JWT administrateur) |
| GET, PUT, DELETE | `/api/v2/admin/cms-pages/{slug}` | Lecture, enregistrement, suppression |
| GET, POST | `/api/v2/admin/cms-templates` | Liste, création |
| GET, PUT, DELETE | `/api/v2/admin/cms-templates/{id}` | Lecture, enregistrement, suppression (409 pour le défaut) |
| POST | `/api/v2/admin/cms-templates/{id}/duplicate` | Copie en brouillon, sans affectation |
| GET, PUT | `/api/v2/admin/cms-templates/{id}/assignments` | Affectations (`productCodes`, `taxonCodes`) |

Contrat détaillé : `docs/API_FRONT.md` §11 (templates et blocs liés). Front : `front/src/lib/blocks/` (rendu, blocs liés dans `blocks/bound/`, `TemplateView.svelte`), `front/src/lib/editor/` (builder), routes `[slug]` et `cms-admin`.

Ajouter ou modifier un bloc libre : la règle des fichiers à toucher ensemble est dans `AGENTS.md` (section « Pages CMS »).

## Ce qui est simulé

- Visuels CMS fixes (`public/yack/cms`), pas de médiathèque.
- Une seule version par page ou template (enregistrer en brouillon dépublie), pas d'historique.

## Tests

`tests/Yack/Content/Domain/PageBlocksTest.php` (dont l'arbre des conteneurs et ses refus), `BlockPathTest.php`, `BlockTypeTest.php`.
