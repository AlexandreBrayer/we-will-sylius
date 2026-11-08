# Contexte Assistance : manuels et notices d'un appareil

## Rôle

Un installateur ou le SAV donne une référence, un modèle ou un n° de série (ou photographie la plaque) et obtient les manuels officiels du modèle exact ; à défaut, ceux de la série ou du même type d'appareil, en le disant. Les documents s'ouvrent par leur lien vers le PDF officiel : aucun PDF dans le dépôt. Décisions : `docs/adr/0008-module-assistance.md`.

Le contexte est retirable d'un bloc ; il ne porte pas la base « Nos documentations » ni l'assistant de la fiche produit, qui sont au contexte Documentation.

## Domain

| Classe | Rôle |
|---|---|
| `Domain/ModelReference` | Référence lue ou saisie → forme canonique, découpage MHI (préfixe, puissance, série, suffixe), correction des confusions de lecture |
| `Domain/ReferenceBrand` | Marque déduite du préfixe de la référence (MHI, Yack, RHOSS), toujours dite « déduite », jamais « lue » |
| `Domain/Manual` | Un document : id, titre, type, langues, lien, officiel, n° de manuel, année, pages, modèles et codes catalogue couverts, texte indexé ou non |
| `Domain/ManualType` | Types de document, dans l'ordre d'affichage, avec leur libellé |
| `Domain/ManualProduct` | Produit fini du catalogue cité par la base : code, nom, marque, famille (autres puissances du même modèle) |
| `Domain/MatchLevel`, `Domain/MatchReason`, `Domain/ManualMatch` | Niveau (exact, série, famille, aucun), raison, document retenu |
| `Domain/ManualMatcher` | Règles de correspondance (ci-dessous), sans dépendance |
| `Domain/InvalidManual` | Document ou base refusés au chargement (id du document en cause) |

`Manual` refuse un id hors `^[a-z0-9][a-z0-9-]*$`, un lien qui n'est pas en https et un document qui ne couvre aucun modèle ni code catalogue.

## Application

| Classe | Rôle |
|---|---|
| `Application/FindManuals` | Recherche : saisie libre (`q`) ou référence et n° de série validés (`reference`, `serial`) vers `ManualSearch`. Bornes de la saisie (`FindManuals::problems`) : l'un des trois champs requis, 100 caractères au plus chacun |
| `Application/ManualSearch` | Résultat : référence comprise et sa marque, n° de série et produits finis du parc, conflit, fiche du catalogue, groupes de documents par niveau et par source, choix de modèles (8 au plus) et `moreChoices` ; `level()`, `askable()` |

Saisie libre, dans l'ordre : n° de série (ou ServiceCode) connu du parc installé ; sinon référence découpable ou présente dans la base (modèles, codes catalogue, produits) ; sinon mots cherchés dans le nom et le code des produits de la base (tous les mots, accents et casse ignorés) : les 8 premiers dans l'ordre de la base proposés au choix, `moreChoices` s'il y en a davantage ; sinon aucun document (la saisie reste une référence si elle tient en un mot). `q` l'emporte sur `reference` et `serial`.

N° de série : connu du parc, il donne les produits finis de l'appareil, cherchés chacun comme une référence (source `serial`) ; un document y apparaît une fois, à son meilleur niveau, et le niveau famille seulement si aucun produit n'a de document exact ou série. Si une référence est aussi saisie et n'est aucun de ces produits : conflit, et les documents de la référence s'ajoutent (source `reference`). Inconnu : rendu tel quel, sans autre effet. Groupes triés par niveau, raison, puis source (n° de série d'abord).

Marque de la référence : celle du catalogue (produit de la base, sinon fiche du catalogue), sinon déduite du préfixe (`ReferenceBrand`). Fiche du catalogue : celle de la référence, sinon du premier produit fini du parc qui en a une.

## Ports et adapters

| Port (`Domain/`) | Adapter | Rôle |
|---|---|---|
| `ManualLibrary` | `Infrastructure/Library/JsonManualLibrary` (alias) | Lit l'index versionné manuels.json du dossier data/yack/assistance, une fois par requête. Fichier absent : base vide, sans erreur. Un document invalide arrête le chargement (`InvalidManual` avec son id) |

L'index est produit hors du dépôt par le générateur de la base (à partir de la recherche des manuels publics) ; le dépôt ne contient que sa sortie. Format : `{version, source, generatedFrom, types, documents: [{id, title, brand, type, languages, url, domain, official, manualNumber, year, pages, models, catalogCodes, verified, text, indexedPages}], products: [{code, name, brand, family}]}`. Exemple complet : `tests/Yack/Assistance/Infrastructure/Library/manuels-test.json`.

Autres adapters : `Infrastructure/Api/ManualSearchProvider` et `Infrastructure/Api/ManualSearchView` (recherche), `Infrastructure/Api/AssistanceSwitch` (interrupteur).

## Correspondance « référence → documents »

### Normalisation (`ModelReference::fromInput`)

1. Majuscules ; tirets Unicode (U+2010 à U+2015, U+2212) ramenés à `-` ; espaces autour d'un tiret retirées ; libellé de tête retiré (`MODEL`, `MODÈLE`, `MODELE`, `TYPE`, `RÉF.`, `REF`, suivis de `:` ou d'une espace) ; ponctuation finale retirée.
2. Une espace avant un suffixe connu en fin de saisie devient un tiret (`SRK25ZS W` donne `SRK25ZS-W`), les autres espaces sont retirées. Suffixes connus : `W` suivi de 0 à 3 lettres ou chiffres (`WF`, `WFT`, `W1`…), `A`, `E`, `S`. Le générateur de la base applique les mêmes étapes 1 et 2 aux modèles des documents (`ModelReference::normalize`).
3. Découpage MHI si le préfixe est connu (`ModelReference::PREFIXES`, le plus long d'abord) : `^(PRÉFIXE)(\d{2,3})([A-Z][A-Z0-9]*?)(?:-([A-Z0-9]+))?$`. `SRK25ZS-WFT` : SRK, 25, ZS, WFT ; `FDC224KXZME1` : FDC, 224, KXZME1, sans suffixe ; `FDUT15KXE6F-W` : FDUT, 15, KXE6F, W.
4. Correction de lecture, seulement si le découpage échoue sans elle : dans la zone puissance `O→0`, `I→1`, `L→1`, `S→5`, `B→8` (trois chiffres essayés avant deux) ; dans le préfixe l'inverse (`5RK` lu, `SRK` compris). La référence corrigée porte `corrected: true` ; `read` garde la saisie (« lu : SRK2SZS-W, compris : SRK25ZS-W »).
5. Une référence non découpable (`FDCW-VNX-A`, `RC-EX3D`, `YORABTM200`, codes Yack) reste `structured: false` : seule la correspondance exacte s'applique, plus la famille du catalogue.

Saisie vide ou de plus de 40 caractères : `null`. Les modèles des documents sont lus par `ModelReference::fromDocument` (mêmes règles, sans correction de lecture).

Comparaisons : `sameModelAs` (même préfixe, puissance et série), `sameSeriesAs` (même préfixe, série et suffixe de base, c'est-à-dire la lettre avant options et chiffre : `W` pour `WFT` ou `W1`), `sameTypeAs` (même préfixe). Une référence non découpable n'est jamais « du même modèle, de la même série ou du même type ».

### Niveaux (`ManualMatcher::match`)

Un document n'apparaît qu'au meilleur niveau qu'il atteint. Les modèles et les codes catalogue d'un document comptent tous deux.

| Niveau | Raison | Règle |
|---|---|---|
| exact | `reference_identique` | la forme canonique figure dans les modèles ou les codes catalogue du document |
| série | `variante_suffixe` | même préfixe, puissance et série, autre suffixe (`SRK25ZS-W` lu, document `SRK25ZS-WF`) ; une référence sans suffixe (`SRK25ZS`) tombe ici |
| série | `autre_puissance` | même préfixe, série et suffixe de base, autre puissance (`SRK50ZS-W` lu, document `SRK20ZS-W`) ; ou la référence est dans la famille catalogue (`ManualProduct::codes`) d'un modèle couvert par le document, même non découpable |
| famille | `meme_type_appareil` | seulement si aucun document exact ou série : même préfixe, autre série ; types utilisation, télécommande et brochure seulement ; 5 documents au plus |
| aucun | | liste vide (`ManualMatcher::levelOf` donne `aucun`) |

Ordre : niveau, raison (variante avant autre puissance), puis dans un groupe type (ordre de `ManualType`), document en français d'abord, officiel d'abord, année décroissante (sans année en dernier), titre, id. `MatchLevel::isReliable()` : seuls les niveaux exact et série peuvent nourrir une réponse aux questions.

Le n° de série ne sert jamais à deviner un modèle par son format : il n'existe pas de décodage public fiable des n° de série MHI.

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/yack/assistance/manuals?q=` ou `?reference=&serial=` | Public. Référence comprise, n° de série, fiche produit, groupes `{level, reason, source, documents}`, `choices`, `moreChoices`, `askable` ; 422 sur le champ (vide, ou plus de 100 caractères) |

Contrat détaillé : `docs/API_FRONT.md` §13. Interrupteur `YACK_ASSISTANCE` (bloc yack/assistance de `.env`, `1` par défaut) : à `0`, `AssistanceSwitch::assertEnabled()`, appelé en premier par chaque provider, répond 404.

## Interfaces exposées aux autres contextes

Aucune. Lectures faites dans d'autres contextes (ADR 008) :

- `AfterSales\Application\ApplianceModelsInterface` : produits finis d'un n° de série (ou ServiceCode) du parc installé ;
- `Catalog\Application\ProductReferenceReaderInterface` : fiche du catalogue d'une référence (donnée `Catalog\Domain\ProductReference`).

## Ce qui est simulé

- Rien dans la correspondance : les règles portent sur les vraies références MHI, Yack et RHOSS du catalogue.
- La base : URL publiques trouvées par une recherche web, vérifiées selon le champ `verified` (`pdf_lu`, `webfetch`, `non`) ; leur disponibilité se contrôle hors de l'environnement de développement.
- La marque déduite de la référence (`ReferenceBrand`) est une déduction, affichée comme telle.
- Le parc installé lu par `ApplianceModelsInterface` : n° de série de démonstration (`YK19KAZ35S004567` et les autres de `docs/POC_YACK.md`).

Supprimer ce contexte : retirer `src/Yack/Assistance/`, `tests/Yack/Assistance/`, le dossier data/yack/assistance, `docs/adr/0008-module-assistance.md`, le bloc yack/assistance de `.env` et la section 13 de `docs/API_FRONT.md`. `ApplianceModelsInterface` d'AfterSales reste (utile sans le module).
