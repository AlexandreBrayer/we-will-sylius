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
- **Validation** : `Domain/PageBlocks` (bloc inconnu, propriété inconnue, valeur hors liste, bloc lié hors de son type, bloc obligatoire absent ou en double : `Domain/InvalidPageBlocks`, réponse 422).
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

Ajouter ou modifier un bloc libre touche toujours, dans le même commit : `Domain/BlockType.php`, `front/src/lib/blocks/types.ts`, `registry.ts`, `components.ts` et le composant `.svelte`, les styles `.yack-cms-*`, `tests/Yack/Content/Domain/PageBlocksTest.php` (`AGENTS.md`).

## Ce qui est simulé

- Visuels CMS fixes (`public/yack/cms`), pas de médiathèque.
- Une seule version par page ou template (enregistrer en brouillon dépublie), pas d'historique.

## Tests

`tests/Yack/Content/Domain/PageBlocksTest.php`.
