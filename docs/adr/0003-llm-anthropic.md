# ADR 0003 : fonctions intelligentes sur l'API Anthropic

- Statut : proposé (lot 2, ticket IA-01, à valider par Thomas et Alexandre)
- Contexte : décisions d'Aurélien du 07/11/2026. Les fonctions intelligentes sont **fondues dans le site** là où elles servent (SAV, fiche produit, panier, inscription), recensées sur une page index, et marquées par une couleur d'accent qui leur est propre. Priorités : la pièce par une photo de la plaque, la lecture de documents (devis, Kbis), l'assistant documentation (RAG).

## Décision

1. **Un port, deux adapters par fonction.** Chaque contexte déclare son port dans son `Domain` (ex. `AfterSales\Domain\NameplateReader`). L'adapter factice, déterministe, est actif par défaut : le POC se démontre sans clé ni réseau. L'adapter réel s'appuie sur `Common\Domain\LanguageModel`.
2. **Un seul client** : `Common\Infrastructure\Anthropic\AnthropicClient` (Messages API, `anthropic-version: 2023-06-01`). La réponse est forcée par un outil unique dont le schéma JSON est celui attendu : pas de texte libre. Chaque contexte revalide la réponse avant usage (une référence ou une pièce inventée par le modèle est écartée).
3. **Sélection** : `YACK_AI_ADAPTER=fake|anthropic` (défaut `fake`). Modèle choisi par `YACK_AI_MODEL`, sans valeur par défaut : l'adapter réel sans modèle lève une erreur de configuration au premier appel.
4. **Garde-fous**
   - délai de 20 s ; réponse 429, 5xx, délai ou réseau : `AiUnavailable`, transformée en message clair par la fonction (ex. « Lecture indisponible : saisissez le numéro de série ») ;
   - plafond quotidien d'appels réels pour tout le site (`YACK_AI_DAILY_CAP`, 200 par défaut) ; au-delà, aucun appel ;
   - journal : fonction appelante, statut HTTP, durée. **Jamais** le contenu envoyé ni reçu ;
   - photos réencodées par `ExifStripper` (GD) avant envoi : métadonnées EXIF supprimées (position GPS, appareil), 1 600 px au plus.
5. **Secrets** : `ANTHROPIC_API_KEY` dans `.env.local` seulement, jamais côté front (le navigateur ne parle qu'au front SvelteKit, qui ne parle qu'à Sylius).

## Données envoyées, par fonction

| Fonction | Envoyé | Jamais envoyé |
|---|---|---|
| Pièce par une photo | la photo de la plaque, sans EXIF | le compte client, l'adresse |
| Lecture d'un devis | le PDF déposé | le compte client, les prix Yack |
| Kbis | le PDF du Kbis | le reste du dossier client |
| Assistant documentation | la question et les seuls passages de notice retrouvés | le compte client, l'historique |

## Conséquences

- Coût : un appel par usage, plafonné par jour. Ordre de grandeur à mesurer en recette sur la clé de Purjus (aucun chiffre avancé ici).
- RGPD : les fonctions qui envoient des documents de clients (Kbis, devis, photos) restent sur l'adapter factice tant que le contrat de sous-traitance avec Anthropic et la durée de conservation chez le fournisseur ne sont pas vérifiés (HYPOTHÈSE à vérifier).
- Remplaçable : un autre fournisseur, ou un modèle hébergé par Purjus, est un nouvel adapter de `LanguageModel`, sans toucher aux contextes.
