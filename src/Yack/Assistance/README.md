# Contexte Assistance : manuels et notices d'un appareil

## Rôle

Un installateur ou le SAV donne une référence, un modèle ou un n° de série (ou photographie la plaque) et obtient les manuels officiels du modèle exact ; à défaut, ceux de la série ou du même type d'appareil, en le disant. Les documents s'ouvrent par leur lien vers le PDF officiel : aucun PDF dans le dépôt. Décisions : `docs/adr/0008-module-assistance.md`.

Le contexte est retirable d'un bloc ; il ne porte pas la base « Nos documentations » ni l'assistant de la fiche produit, qui sont au contexte Documentation.

## Domain

| Classe | Rôle |
|---|---|
| `Domain/ModelReference` | Référence lue ou saisie → forme canonique, découpage MHI (préfixe, puissance, série, suffixe), correction des confusions de lecture |
| `Domain/ReferenceBrand` | Marque déduite du préfixe de la référence (MHI, Yack, RHOSS), toujours dite « déduite », jamais « lue » |
| `Domain/PrintedBrand` | Marque imprimée sur la plaque ramenée au nom du catalogue quand elle le désigne : même nom à la casse, aux espaces et à la ponctuation près (`YACK` donne `Yack`), ou raison sociale connue (`Mitsubishi Heavy Industries` donne `MHI`) ; toute autre marque reste telle que lue |
| `Domain/Manual` | Un document : id, titre, type, langues, lien, officiel, n° de manuel, année, pages, modèles et codes catalogue couverts, texte indexé ou non |
| `Domain/ManualType` | Types de document, dans l'ordre d'affichage, avec leur libellé |
| `Domain/ManualProduct` | Produit fini du catalogue cité par la base : code, nom, marque, famille (autres puissances du même modèle) |
| `Domain/MatchLevel`, `Domain/MatchReason`, `Domain/ManualMatch` | Niveau (exact, série, famille, aucun), raison, document retenu |
| `Domain/ManualMatcher` | Règles de correspondance (ci-dessous), sans dépendance |
| `Domain/InvalidManual` | Document ou base refusés au chargement (id du document en cause) |
| `Domain/ManualAnswer` | Réponse à une question aux manuels : texte, sources (document et page du PDF), `simulated`, `fromTable`. Une réponse trouvée cite au moins une page ; les seules réponses sans source sont « Je ne trouve pas la réponse dans les manuels indexés de ce modèle. » (`notFound`) et « Je ne trouve pas la réponse : aucun manuel de ce modèle n'est indexé pour les questions… » (`notIndexed`). Une source tirée d'un tableau ajoute l'avertissement `TABLE_WARNING` au texte |

`Manual` refuse un id hors `^[a-z0-9][a-z0-9-]*$`, un lien qui n'est pas en https et un document qui ne couvre aucun modèle ni code catalogue.

## Application

| Classe | Rôle |
|---|---|
| `Application/FindManuals` | Recherche : saisie libre (`q`) ou référence et n° de série validés (`reference`, `serial`) vers `ManualSearch`. Bornes de la saisie (`FindManuals::problems`) : l'un des trois champs requis, 100 caractères au plus chacun |
| `Application/ManualSearch` | Résultat : référence comprise et sa marque, n° de série et produits finis du parc, conflit, fiche du catalogue, groupes de documents par niveau et par source, choix de modèles (8 au plus) et `moreChoices` ; `level()`, `askable()` |
| `Application/ReadPlate` | Photo de la plaque vers `PlateReading`, sans aucune recherche de documents : lecture d'AfterSales (`ReadNameplateInterface`), référence ramenée à sa forme canonique (`ModelReference::fromInput`), marque. Photo refusée (`NameplatePhotoRefused`) et lecture indisponible (`AiUnavailable`) remontent à l'appelant |
| `Application/AskManuals` | Question aux manuels d'une référence (voir « Questions aux manuels »). Bornes (`AskManuals::problems`) : question de 3 à 300 caractères, référence requise, 40 caractères au plus |
| `Application/PlateReading` | Ce que voit l'écran de validation : n° de série, référence lue (`referenceRead`) et comprise (`reference`), désignation, marque et son origine, `simulated` ; `isEmpty()` quand ni n° de série ni référence ne sont lus |

Saisie libre, dans l'ordre : n° de série (ou ServiceCode) connu du parc installé ; sinon référence découpable ou présente dans la base (modèles, codes catalogue, produits) ; sinon mots cherchés dans le nom et le code des produits de la base (tous les mots, accents et casse ignorés) : les 8 premiers dans l'ordre de la base proposés au choix, `moreChoices` s'il y en a davantage ; sinon aucun document (la saisie reste une référence si elle tient en un mot). `q` l'emporte sur `reference` et `serial`.

N° de série : connu du parc, il donne les produits finis de l'appareil, cherchés chacun comme une référence (source `serial`) ; un document y apparaît une fois, à son meilleur niveau, et le niveau famille seulement si aucun produit n'a de document exact ou série. Si une référence est aussi saisie et n'est aucun de ces produits : conflit, et les documents de la référence s'ajoutent (source `reference`). Inconnu : rendu tel quel, sans autre effet. Groupes triés par niveau, raison, puis source (n° de série d'abord).

Marque de la référence : celle du catalogue (produit de la base, sinon fiche du catalogue), sinon déduite du préfixe (`ReferenceBrand`). Fiche du catalogue : celle de la référence, sinon du premier produit fini du parc qui en a une.

Lecture de la plaque (`ReadPlate`) : rien n'est cherché ; l'écran de validation montre ce qui est lu, la personne confirme ou corrige, et c'est seulement alors que la recherche est lancée. Marque : celle imprimée sur la plaque, au nom du catalogue quand elle le désigne (`PrintedBrand`, `brandSource` : `plaque`), sinon déduite du préfixe de la référence lue (`deduite`), sinon aucune. Une lecture illisible donne des champs vides (la marque reste si elle est imprimée).

## Ports et adapters

| Port (`Domain/`) | Adapter | Rôle |
|---|---|---|
| `ManualLibrary` | `Infrastructure/Library/JsonManualLibrary` (alias) | Lit l'index versionné manuels.json du dossier data/yack/assistance, une fois par requête. Fichier absent : base vide, sans erreur. Un document invalide arrête le chargement (`InvalidManual` avec son id) |
| `ManualAnswerer` | `Infrastructure/Question/DocumentationManualAnswerer` (alias) | Répond à partir du texte indexé des manuels donnés, par le moteur du contexte Documentation (`AnswerFromNoticesInterface`) |

L'index est produit hors du dépôt par le générateur de la base (à partir de la recherche des manuels publics) ; le dépôt ne contient que sa sortie. Format : `{version, source, generatedFrom, types, documents: [{id, title, brand, type, languages, url, domain, official, manualNumber, year, pages, models, catalogCodes, verified, text, indexedPages}], products: [{code, name, brand, family}]}`. Exemple complet : `tests/Yack/Assistance/Infrastructure/Library/manuels-test.json`.

Autres adapters : `Infrastructure/Api/ManualSearchProvider` et `Infrastructure/Api/ManualSearchView` (recherche), `Infrastructure/Api/PlateReadingProcessor` et `Infrastructure/Api/PlateReadingView` (lecture de la plaque : erreurs d'envoi, 20 Mo, fichier temporaire supprimé, photo jamais conservée), `Infrastructure/Api/ManualQuestionInput`, `Infrastructure/Api/ManualQuestionProcessor` et `Infrastructure/Api/ManualAnswerView` (question aux manuels), `Infrastructure/Api/AssistanceSwitch` (interrupteur), `Infrastructure/Question/MarkdownManualTexts` (lecture des textes indexés) et `Infrastructure/Question/ManualVocabulary` (codes d'erreur, termes métier français-anglais).

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

## Questions aux manuels

Déroulé (`AskManuals`, puis `DocumentationManualAnswerer`) :

1. la référence est lue comme à la recherche (`ModelReference::fromInput`) et `ManualMatcher` donne ses documents ; seuls ceux de niveau exact ou série (`MatchLevel::isReliable()`) qui ont un texte indexé sont lus. Aucun : « Je ne trouve pas la réponse : aucun manuel de ce modèle n'est indexé pour les questions… », sans appel au moteur ni au modèle (cas des niveaux famille et aucun, et des modèles dont les manuels n'ont que leur lien) ;
2. chaque manuel devient une notice du contexte Documentation, titrée par l'id du document : court et unique, le modèle le recopie comme source sans le reformuler, et le titre réel (souvent très long) est retrouvé par l'id après la réponse. Passages de son texte avec leur page du PDF, codes catalogue ; ses modèles et codes nomment l'appareil et ne comptent pas comme mots de la question ;
3. `AnswerFromNoticesInterface` cherche les passages (BM25 lexical, 3 au plus, règle de pertinence de `RetrievePassages`), les donne au seul answerer (démonstration par défaut, modèle réel avec `YACK_AI_ADAPTER`) et retire toute source hors des passages lus (une source réécrite par le modèle, à la casse, aux accents, à la ponctuation ou aux espaces près, est ramenée à l'id : `Answer::restrictedTo`) ; rien de pertinent : « Je ne trouve pas la réponse dans les manuels indexés de ce modèle. », sans appel au modèle ;
4. chaque source devient le document et sa page (`{documentId, title, page, url}` dans l'API).

Textes indexés (`MarkdownManualTexts`) : fichier `textes/<id>.md` du dossier data/yack/assistance désigné par le champ `text` du document. En-tête entre deux lignes `---` (ignoré : la base fait foi), `[page N]` (page du PDF), titres `#` (section des paragraphes suivants, remise à zéro à chaque page), un passage par paragraphe ou par élément de liste. Le texte vient des pages choisies du PDF (champ `indexedPages`), pas du manuel entier.

Défauts du texte extrait, et ce qui en est fait (mesuré sur les questions de recette, plus bas) :

- **Codes d'erreur** : les manuels MHI écrivent « E 5 », « E 16 » ; l'analyse française de Documentation jette la lettre seule et prend le nombre pour une quantité. `ManualVocabulary::glueCodes` accole une lettre isolée et un nombre de 1 à 3 chiffres (« a », « à » et « y » restent des mots), dans les passages comme dans la question (le texte rendu porte `E16`). La question est aussi cherchée par ses seuls codes (« E16 ? ») : sans cela, un passage qui répète « error code » passe devant celui qui nomme le code.
- **Tableaux aplatis** (au moins 3 séparateurs ` | ` dans le passage : on en trouve sur toutes les pages indexées du manuel technique, aucun dans les deux manuels en français) : les cellules de plusieurs lignes sont mélangées mais restent côte à côte. Ils sont lus ; une réponse qui cite une page où il y en a un porte l'avertissement « Attention : passage tiré d'un tableau du manuel… vérifiez la valeur sur le PDF, à la page citée. ».
- **Grilles éclatées** (page où au moins 5 titres différents se répètent : page 38 du manuel de l'utilisateur SRK ZS-W, « Clignote-2 fois »…) : le lien entre une cellule et sa ligne est perdu. Ces passages ne sont pas lus : sur « Le voyant RUN clignote, que faire ? », ils donnaient « Le voyant RUN clignote en continu », sans la cause ni le remède.
- **Manuel en anglais** (manuel technique SRK/SRC ZS-W) : l'analyse est française (mots vides, racines à 6 lettres). Quand un manuel lu n'est pas en français, la question est aussi cherchée sous une forme anglaise : termes métier traduits par la petite table de `ManualVocabulary` (filtre / filter, voyant / lamp light, clignote / flash blink, erreur / error, longueur / length, liaison / line piping…), plus les mots gardés tels quels (codes, nombres, mots en capitales comme RUN). Les passages trouvés par chaque formulation sont fusionnés à tour de rôle ; l'answerer reçoit la question d'origine.

Mesure sur les deux manuels du modèle SRK25ZS-W (manuel de l'utilisateur en français, manuel technique en anglais), passages retenus dans l'ordre, adapter de démonstration :

| Question | Analyse seule | Codes accolés et cherchés seuls | Avec la forme anglaise (retenu) |
|---|---|---|---|
| Comment nettoyer le filtre ? | FR p34 ×3 | FR p34 ×3 | FR p34 ×3 |
| Le voyant RUN clignote, que faire ? | FR p38, p38, p10 (grille) ; p10, p36, p36 sans la grille | idem | FR p10, EN p82, FR p36 (grille écartée) |
| Que signifie le code erreur E 16 ? | rien | EN p82 | EN p82, p85, p86 |
| Que signifie le code E5 ? | rien | EN p82 | EN p82 |
| Quelle longueur de liaison maximale ? | rien | rien | EN p45 |
| Quelle est la capitale du Portugal ? | rien | rien | rien |

Sans ces ajustements, une question française ne retrouve dans le texte anglais que des mots identiques dans les deux langues (RUN, TIMER) : ni les codes « E 16 » (lettre jetée), ni les valeurs (« longueur », « liaison » absents). La vraie parade est une recherche sémantique (un adapter de `PassageRetriever` dans Documentation) ; la table reste petite et dans ce contexte.

Questions de recette (vrais textes, adapter de démonstration, `DocumentationManualAnswererTest`) :

| Question, référence | Source | Réponse |
|---|---|---|
| Comment nettoyer le filtre ?, SRK25ZS-W | manuel de l'utilisateur, p. 34 | « Ôtez le filtre photocatalytique désodorisant lavable (orange) du porte-filtre et inspectez le filtre… vous pouvez le nettoyer à l'eau… » (la page 33, « Nettoyage du filtre à air », est classée après) |
| Le voyant RUN clignote, que faire ?, SRK25ZS-W | manuel de l'utilisateur, p. 10 | « Les voyants RUN clignotent rapidement lorsque le mode d'opération n'est pas valide. Page 35 » (renvoi du manuel imprimé, gardé par le texte indexé) |
| Que signifie le code erreur E 16 ?, SRK25ZS-W | manuel technique, p. 82 (tableau) | passage du tableau d'autodiagnostic, mot pour mot : « … Indoor fan \| • Defective fan motor, poor \| -1 ON \| E16 \| … motor error \| connector connection … », suivi de l'avertissement |
| Quelle longueur de liaison maximale ?, SRK25ZS-W | manuel technique, p. 45 (tableau) | tableau des limites d'usage, mot pour mot : « … Refrigerant line (one way) length \| Max. 20m \| Max. 25m … » (20 m pour SRK20 à 35, 25 m pour SRK50, d'après l'ordre des colonnes), suivi de l'avertissement |
| Quelle est la capitale du Portugal ?, SRK25ZS-W | aucune | « Je ne trouve pas la réponse dans les manuels indexés de ce modèle. », sans appel |
| Comment nettoyer le filtre ?, FDC224KXZME1 | aucune | « Je ne trouve pas la réponse : aucun manuel de ce modèle n'est indexé… », sans appel |

Mode réel : `YACK_AI_ADAPTER=anthropic`, `ANTHROPIC_API_KEY` et `YACK_AI_MODEL` dans `.env.local` (`docs/adr/0003-llm-anthropic.md`). L'answerer est `AnthropicDocumentationAnswerer` de Documentation, appelé par `Common\Domain\LanguageModel` : il reçoit la question et les 3 passages retenus (texte public des manuels, aucune donnée du client) ; sa consigne lui demande de répondre en français à partir de ces seuls passages et de citer source et page, et ses sources sont revalidées. Ce mode n'est vérifié qu'avec une clé, sur le Mac ; sans clé, `DocumentationManualAnswererTest` fait passer les vrais textes par cet answerer et un faux modèle de langage qui rend l'id du manuel tel quel, puis réécrit (capitales, espaces, point final) : la source est retrouvée dans les deux cas, une page non envoyée est refusée. Le modèle est celui de `YACK_AI_MODEL` (identifiant du modèle chez le fournisseur ; un modèle Opus est possible pour le POC, au prix d'appels plus lents et plus chers) ; le plafond quotidien `YACK_AI_DAILY_CAP` compte ces appels avec ceux de la fiche produit.

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/yack/assistance/manuals?q=` ou `?reference=&serial=` | Public. Référence comprise, n° de série, fiche produit, groupes `{level, reason, source, documents}` (chaque document : `hasText` et `indexedPages`, pages indexées pour les questions, null sans texte), `choices`, `moreChoices`, `askable` ; 422 sur le champ (vide, ou plus de 100 caractères) |
| POST | `/api/v2/shop/yack/assistance/plate-readings` | Public. Photo (multipart, champ `photo`) vers `{serialNumber, referenceRead, reference, model, brand, brandSource, simulated}`, sans recherche ; 422 sur `photo`, 503 « Lecture indisponible : saisissez la référence » |
| POST | `/api/v2/shop/yack/assistance/questions` `{question, reference}` | `ROLE_USER` (visiteur 401). `{answer, sources: [{documentId, title, page, url}], simulated}` ; 422 sur `question` ou `reference` ; 503 « Les manuels ne peuvent pas répondre pour le moment… » |

Contrat détaillé : `docs/API_FRONT.md` §13. Interrupteur `YACK_ASSISTANCE` (bloc yack/assistance de `.env`, `1` par défaut) : à `0`, `AssistanceSwitch::assertEnabled()`, appelé en premier par chaque provider ou processor, répond 404. `compose.yml` passe la même valeur aux services php et front (celle du shell, sinon de `.env`), pour que l'API et le site soient coupés ensemble : `YACK_ASSISTANCE=0 make yack-up`. Une ligne dans `.env.local` ne coupe rien sous Docker (compose ne lit pas ce fichier, et PHP garde la variable reçue de compose). Un visiteur sur `POST …/questions` reçoit 401 même module coupé : le droit `ROLE_USER` de l'opération est contrôlé avant le processor.

## Interfaces exposées aux autres contextes

Aucune. Lectures faites dans d'autres contextes (ADR 008) :

- `AfterSales\Application\ApplianceModelsInterface` : produits finis d'un n° de série (ou ServiceCode) du parc installé ;
- `AfterSales\Application\ReadNameplateInterface` : ce qui est imprimé sur la photo d'une plaque (donnée `AfterSales\Domain\NameplateReading`, exception `AfterSales\Domain\NameplatePhotoRefused`) ;
- `Catalog\Application\ProductReferenceReaderInterface` : fiche du catalogue d'une référence (donnée `Catalog\Domain\ProductReference`) ;
- `Documentation\Application\AnswerFromNoticesInterface` : réponse à une question sur des notices données (données `Documentation\Domain\{Notice, Passage, Question, Answer, Citation}`), lue par `DocumentationManualAnswerer` seulement.

## Ce qui est simulé

- Rien dans la correspondance : les règles portent sur les vraies références MHI, Yack et RHOSS du catalogue.
- La base : URL publiques trouvées par une recherche web, vérifiées selon le champ `verified` (`pdf_lu`, `webfetch`, `non`) ; leur disponibilité se contrôle hors de l'environnement de développement.
- La marque déduite de la référence (`ReferenceBrand`) est une déduction, affichée comme telle.
- La lecture de la plaque : par défaut l'adapter de démonstration d'AfterSales, qui ne reconnaît que les plaques FICTIF de `public/yack/demo/plaques/` (`simulated: true`) ; lecture réelle par le modèle de langage avec `YACK_AI_ADAPTER`.
- Le parc installé lu par `ApplianceModelsInterface` : n° de série de démonstration (`YK19KAZ35S004567` et les autres de `docs/POC_YACK.md`).
- Les questions aux manuels : texte réel des manuels officiels, mais seulement des pages choisies (`indexedPages`) de trois manuels ; réponse par défaut de l'adapter de démonstration de Documentation (le meilleur passage mot pour mot, `simulated: true`, en anglais pour le manuel technique) ; réponse rédigée en mode réel seulement.

## Supprimer ce contexte

Liste exacte (vérifiée par un retrait à blanc : après elle, aucune mention du module ne reste dans `src`, `tests`, `config`, `front/src`, `front/test`, `assets` et `bin` ; et le banc PHP, la conception, svelte-check, le build, les tests node et la compilation SCSS passent).

Supprimer :

- `src/Yack/Assistance/`, `tests/Yack/Assistance/`, le dossier data/yack/assistance, `docs/adr/0008-module-assistance.md` ;
- `front/src/routes/(shop)/assistance/`, `front/src/lib/assistance/` (dont `AssistanceLink.svelte`), `front/src/lib/remote/assistance.remote.ts`, `front/src/lib/server/assistance.ts`, `front/test/assistance.test.ts`, `assets/shop/styles/_assistance.scss`.

Retirer une ligne ou un bloc :

- configuration : bloc yack/assistance de `.env` ; dans `compose.yml`, les lignes `YACK_ASSISTANCE` des services php et front et leur commentaire ; variable `YACK_ASSISTANCE` de `front/src/env.ts` ; `@use 'assistance';` de `assets/shop/styles/yack.scss` ;
- points d'entrée : import et balise `AssistanceLink` de `front/src/routes/(shop)/sav/+page.svelte` ; dans `front/src/routes/+layout.svelte`, import de `assistanceEnabled`, `{@const assistance …}` et `{assistance}` passé à `ShopHeader` ; dans `front/src/lib/layout/ShopHeader.svelte`, `ASSISTANCE_ENTRY`, la prop `assistance`, son ajout aux entrées de *Nos services* et sa mention dans le commentaire du menu ; dans `front/src/routes/(shop)/fonctions-intelligentes/+page.svelte`, import de `assistanceEnabled`, `assistance` et `urlOf` (le lien redevient `fn.url`) ; dans `front/src/lib/smart/functions.ts`, l'entrée `assistant-documentation` rendue à la fiche produit : `why` « Une question sur un appareil, une réponse tirée de ses notices, avec la page source. », `where` « Fiche produit », `url` à `null` (« en cours de branchement » : le corpus de Documentation est vide), `audience` « Comptes connectés (PRO et ECOM) » ;
- documentation du front et de l'API : dans `front/README.md`, la ligne `/assistance` du tableau des routes, le lien « Trouver le manuel d'un appareil » de la ligne `/sav` et « et `/assistance` » après `PhotoPicker.svelte` ; section 13 de `docs/API_FRONT.md` ;
- `docs/POC_YACK.md` : le script « Manuels et notices », la ligne du module dans « Ce qui est réel / ce qui est simulé », et dans « Plaques de démonstration » la colonne « Lecture pour les manuels », la phrase qui la décrit et « , lecture pour les manuels » du titre ;
- `docs/adr/0003-llm-anthropic.md`, tableau des données envoyées : « et lecture de la plaque des manuels » (ligne de la photo), « et questions aux manuels d'un modèle » avec la route et « , ou texte public des manuels officiels des fabricants » (ligne de l'assistant documentation) ;
- `bin/yack-demo-plaques.php` : le paragraphe de commentaire « Cas de la lecture des manuels et notices » et, dans le commentaire de la plaque sans-marque, « : la marque est déduite de la référence » (les plaques ne changent pas) ;
- README des autres contextes : dans `src/Yack/AfterSales/README.md`, la colonne « Lue par » d'`ApplianceModelsInterface` et de `ReadNameplateInterface` (Assistance remplacé par « aucun ») ; dans `src/Yack/Catalog/README.md`, « et par Assistance pour la fiche du catalogue d'une référence (manuels et notices) » ; dans `src/Yack/Documentation/README.md`, la dernière phrase du rôle (manuels officiels pour Assistance), la colonne « Lue par » d'`AnswerFromNoticesInterface` (« aucun »), la fin de « Ce qui est simulé » (manuels des fabricants par Assistance) et la phrase sur Assistance de « Supprimer ce contexte ».

Gardés, utiles et testés sans le module : `ApplianceModelsInterface` et `ReadNameplateInterface` d'AfterSales, la marque de `NameplateReading` (et la plaque sans-marque), `AnswerFromNoticesInterface` et `Citation::looseKey()` de Documentation, `front/src/lib/sav/PhotoPicker.svelte`.

Masquer sans supprimer : `YACK_ASSISTANCE=0` (voir « Ressources API »).
