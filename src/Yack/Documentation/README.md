# Contexte Documentation : assistant documentation

## Rôle

Répondre à une question d'un installateur à partir des notices des appareils, en citant la notice et la page. Fonction intelligente placée sur la fiche produit (encart « Une question sur cet appareil ? »), réservée aux comptes connectés (PRO et ECOM).

Règle métier (Domain) : une réponse cite toujours au moins une source (notice et page). La seule réponse sans source est « Je ne trouve pas la réponse dans la documentation. » (`Answer::notFound()`). `Answer::cited()` refuse une réponse sans source (`AnswerWithoutSource`), `Answer::restrictedTo()` retire toute source qui n'est pas parmi les passages réellement lus.

Déroulé (`Application/AskDocumentation`) :

1. la question (`Domain/Question` : 3 à 300 caractères, code produit facultatif) part à la récupération des passages (`PassageRetriever`, 3 passages au plus) ;
2. aucun passage pertinent : « Je ne trouve pas la réponse dans la documentation. », sans appel au modèle ;
3. sinon, seuls les passages retrouvés sont transmis à `DocumentationAnswerer`, jamais le corpus entier ;
4. la réponse ne garde que les sources prises parmi ces passages ; sans source restante, elle devient « je ne trouve pas ».

## Ports et adapters

| Port (`Domain/`) | Adapter | Rôle |
|---|---|---|
| `DocumentationCorpus` | `Infrastructure/Corpus/MarkdownDocumentationCorpus` | Les notices disponibles : fichiers `data/yack/demo/documentation/*-FICTIF.md` |
| `PassageRetriever` | `Application/RetrievePassages` | Récupération lexicale en PHP pur (voir plus bas) |
| `DocumentationAnswerer` | `Infrastructure/Assistant/DocumentationAnswererSelector` (alias) | Choisit l'adapter selon `YACK_AI_ADAPTER` (`AiMode::fromEnv`) |
| | `Infrastructure/Assistant/FakeDocumentationAnswerer` | Démonstration (défaut) : réponse extractive déterministe, le meilleur passage mot pour mot avec sa source, `simulated: true` |
| | `Infrastructure/Assistant/AnthropicDocumentationAnswerer` | Réel : `Common\Domain\LanguageModel`, consignes strictes, schéma JSON `{answer, sources: [{source, page}]}`, revalidation des sources |

Autres classes : `Domain/{Passage, Notice, Citation, Answer, Question}`, `Application/LexicalAnalyzer` (termes), `Application/DescribeProductDocumentation` (notices d'un produit et question d'exemple).

### Récupération lexicale (`RetrievePassages`)

- Termes (`LexicalAnalyzer`) : minuscules sans accents, mots vides français retirés, pluriel en « s » ou « x » retiré, mots coupés à 6 lettres (« entretien » et « entretenir », « liaison » et « liaisons » donnent le même terme), nombres gardés (« 8 », « e5 »).
- Score BM25 de chaque passage sur le nom du produit de sa notice, sa section et son paragraphe.
- Filtre produit : une question posée depuis une fiche (`productCode`) ne lit que les notices de ce produit.
- Pertinence : les mots de la question absents du nom du produit (« PAC », « Evolys », « 8 », « kW » ne comptent pas) sont les mots de contenu ; un passage est retenu s'il en contient au moins la moitié dans sa section ou son paragraphe, dont un vrai mot (un nombre ou une unité seuls ne suffisent pas).

### Remplacer la récupération par un moteur sémantique

`PassageRetriever` est le seul point de contact entre la question et le corpus. Une recherche sémantique ou hybride (Typesense ou Meilisearch avec vecteurs et mots-clés) se branche par **un adapter** dans `Infrastructure/` (par exemple un `TypesensePassageRetriever`) qui implémente `PassageRetriever` et porte l'alias à la place de `RetrievePassages`. Il doit tenir le même contrat : passages triés du plus pertinent au moins pertinent, `$limit` au plus, filtrés par `productCode`, liste vide quand rien n'est pertinent (la réponse « je ne trouve pas » sans appel au modèle en dépend). L'indexation des notices (une commande console qui lit `DocumentationCorpus` et pousse les passages avec `source`, `page`, `section`, `productCodes`) reste à écrire à ce moment-là ; `AskDocumentation`, le Domain, l'API et le front ne changent pas.

## Format des notices (`MarkdownDocumentationCorpus`)

```
---
title: Notice d'installation Evolys 8 kW (FICTIF)        # source citée
product: PAC pompe à chaleur air/eau monobloc Evolys 8 kW # mots qui désignent l'appareil
products: YK-PAE-M08                                      # codes du catalogue, séparés par des virgules
example: Quelle est la longueur maximale de liaison hydraulique ?
---
[page 12]
## Liaisons entre l'unité extérieure et le module intérieur
La longueur maximale de la liaison hydraulique…
```

Un passage = un paragraphe (lignes jusqu'à une ligne vide) ou un élément de liste (`- `). Les titres `#` donnent la section des passages qui suivent. Les lignes `>` (avertissement « SPÉCIMEN FICTIF ») ne sont pas indexées. Texte avant le premier `[page N]` : notice refusée.

## Ressources API

| Méthode | Route | Droit | Effet |
|---|---|---|---|
| POST | `/api/v2/shop/yack/documentation-questions` `{question, productCode?}` | `ROLE_USER` (PRO et ECOM), visiteur 401 | 200 `{answer, sources: [{source, page}], simulated}` ; 422 question vide, de moins de 3 ou de plus de 300 caractères, code produit invalide ; 503 modèle indisponible |
| GET | `/api/v2/shop/yack/documentation-coverages/{productCode}` | `ROLE_USER`, visiteur 401 | 200 `{productCode, notices: [titres], exampleQuestion}` (liste vide et `null` sans notice) |

Groupes (ADR 011) : `documentation_question:write` (entrée), `documentation_question:item` (réponse), `documentation_coverage:list` et `documentation_coverage:item`.

Droit retenu : `is_granted('ROLE_USER')`, comme les demandes d'ouverture à la commande. Tout client connecté de la boutique a un profil PRO ou ECOM (`Account\Domain\CustomerProfile`) : `ROLE_USER` couvre exactement ces deux profils, sans ajouter de permission dans `Account`. Quand la documentation dépend du profil (`canSeeDocumentation`), l'expression devient la permission de `CommercePermission`.

Front : fiche produit `/produit/<slug>` (`front/src/lib/documentation/DocumentationPanel.svelte`, `front/src/lib/remote/documentation.remote.ts`, logique dans `front/src/lib/server/documentation.ts`).

## Interfaces exposées aux autres contextes

Aucune. Lit `Common\Domain\{LanguageModel, AiInput, AiMode, AiUnavailable}` (contexte partagé).

## Ce qui est simulé

- Le corpus : trois notices inventées (Evolys 8 kW, Kaze muraux 2,5 à 5,0 kW, Aquaflow 200 et 270 L), marquées « SPÉCIMEN FICTIF, DÉMO PURJUS » et « (FICTIF) » dans leur titre. Les vraies notices de Yack (PDF du flux catalogue) demandent un adapter `DocumentationCorpus` qui extrait le texte page par page.
- L'adapter par défaut `FakeDocumentationAnswerer` (réponse `simulated: true`, repère « démonstration » sur le site). L'adapter réel ne s'active qu'avec `YACK_AI_ADAPTER=anthropic`, `ANTHROPIC_API_KEY` et `YACK_AI_MODEL` dans `.env.local` (`docs/adr/0003-llm-anthropic.md`).

Supprimer ce contexte : retirer `src/Yack/Documentation/`, `tests/Yack/Documentation/`, `data/yack/demo/documentation/`, l'encart de la fiche produit et `front/src/lib/{documentation,remote/documentation.remote.ts,server/documentation.ts}`.
