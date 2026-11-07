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

## Ports et adapters

| Port (`Domain/`) | Adapter | Rôle |
|---|---|---|
| `ManualLibrary` | `Infrastructure/Library/JsonManualLibrary` (alias) | Lit l'index versionné manuels.json du dossier data/yack/assistance, une fois par requête. Fichier absent : base vide, sans erreur. Un document invalide arrête le chargement (`InvalidManual` avec son id) |

L'index est produit hors du dépôt par le générateur de la base (à partir de la recherche des manuels publics) ; le dépôt ne contient que sa sortie. Format : `{version, source, generatedFrom, types, documents: [{id, title, brand, type, languages, url, domain, official, manualNumber, year, pages, models, catalogCodes, verified, text, indexedPages}], products: [{code, name, brand, family}]}`. Exemple complet : `tests/Yack/Assistance/Infrastructure/Library/manuels-test.json`.

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

## Interfaces exposées aux autres contextes

Aucune. Le contexte ne lit aucun autre contexte.

## Ce qui est simulé

- Rien dans la correspondance : les règles portent sur les vraies références MHI, Yack et RHOSS du catalogue.
- La base : URL publiques trouvées par une recherche web, vérifiées selon le champ `verified` (`pdf_lu`, `webfetch`, `non`) ; leur disponibilité se contrôle hors de l'environnement de développement.
- La marque déduite de la référence (`ReferenceBrand`) est une déduction, affichée comme telle.

Supprimer ce contexte : retirer `src/Yack/Assistance/`, `tests/Yack/Assistance/`, le dossier data/yack/assistance et `docs/adr/0008-module-assistance.md`.
