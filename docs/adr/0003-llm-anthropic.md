# ADR 0003 : fonctions intelligentes, fournisseurs de modèles (Mistral, Anthropic)

- Statut : proposé (lot 2, ticket IA-01, à valider par Thomas et Alexandre)
- Contexte : décisions d'Aurélien du 07/11/2026. Les fonctions intelligentes sont **fondues dans le site** là où elles servent (SAV, fiche produit, panier, inscription), recensées sur une page index, et marquées par une couleur d'accent qui leur est propre. Priorités : la pièce par une photo de la plaque, la lecture de documents (devis, Kbis), l'assistant documentation (RAG).

## Décision

1. **Un port, deux adapters par fonction.** Chaque contexte déclare son port dans son `Domain` (ex. `AfterSales\Domain\NameplateReader`). L'adapter factice, déterministe, est actif par défaut : le POC se démontre sans clé ni réseau. L'adapter réel s'appuie sur `Common\Domain\LanguageModel`.
2. **Deux fournisseurs derrière le même port**, choisis par `Common\Infrastructure\Ai\LanguageModelSelector` :
   - `Mistral\MistralClient` (choix d'Aurélien du 07/11/2026 : fournisseur français, hébergement européen) : chat completions avec réponse forcée au schéma JSON (`response_format` `json_schema`) ; un PDF passe d'abord par l'OCR de Mistral (`MISTRAL_OCR_MODEL`), son texte est ensuite envoyé au modèle ;
   - `Anthropic\AnthropicClient` (Messages API, `anthropic-version: 2023-06-01`) : réponse forcée par un outil unique dont le schéma JSON est celui attendu.
   Pas de texte libre dans les deux cas. Chaque contexte revalide la réponse avant usage (une référence ou une pièce inventée par le modèle est écartée).
3. **Sélection** : `YACK_AI_ADAPTER=fake|mistral|anthropic` (défaut `fake`). Modèle choisi par `YACK_AI_MODEL`, sans valeur par défaut : l'adapter réel sans modèle lève une erreur de configuration au premier appel. Les formats de requête Mistral sont à confirmer par un test `external` avec une vraie clé. Une fonction qui envoie une image (pièce par une photo) demande un modèle qui lit les images : chez Mistral, un modèle « vision » (Mistral Small 3.2, Medium 3.1, Large 3), l'image partant en `image_url` (URL `data:` en base64) ; chez Anthropic, tout modèle Claude actuel.
4. **Garde-fous**
   - délai de 20 s ; réponse 429, 5xx, délai ou réseau : `AiUnavailable`, transformée en message clair par la fonction (ex. « Lecture indisponible : saisissez le numéro de série ») ;
   - plafond quotidien d'appels réels pour tout le site (`YACK_AI_DAILY_CAP`, 200 par défaut) ; au-delà, aucun appel ;
   - journal : fonction appelante, statut HTTP, durée. **Jamais** le contenu envoyé ni reçu ;
   - photos réencodées par `ExifStripper` (GD) avant envoi : métadonnées EXIF supprimées (position GPS, appareil), 1 600 px au plus.
5. **Secrets** : `MISTRAL_API_KEY`, `ANTHROPIC_API_KEY` dans `.env.local` seulement, jamais côté front (le navigateur ne parle qu'au front SvelteKit, qui ne parle qu'à Sylius).

## Données envoyées, par fonction

| Fonction | Envoyé | Jamais envoyé |
|---|---|---|
| Pièce par une photo (`sav.nameplate`) | la photo de la plaque ou de l'étiquette, réencodée par `ExifStripper` (sans EXIF, 1 600 px au plus) | le compte client, l'adresse, l'adresse IP, le nom du fichier |
| Lecture d'un devis | le PDF déposé | le compte client, les prix Yack |
| Kbis | le PDF du Kbis | le reste du dossier client |
| Assistant documentation | la question et les seuls passages de notice retrouvés | le compte client, l'historique |

## Conséquences

- Coût : un appel par usage, plafonné par jour. Ordre de grandeur à mesurer en recette sur la clé de Purjus (aucun chiffre avancé ici).
- RGPD : les fonctions qui envoient des documents de clients (Kbis, devis, photos) restent sur l'adapter factice tant que le contrat de sous-traitance avec Anthropic et la durée de conservation chez le fournisseur ne sont pas vérifiés (HYPOTHÈSE à vérifier).
- Remplaçable : un autre fournisseur, ou un modèle hébergé par Purjus, est un nouvel adapter de `LanguageModel`, sans toucher aux contextes.
- **Qualité et français** : on ne s'en remet pas au fournisseur. Un jeu de questions de recette (vraies questions de techniciens, réponse attendue et page source) est rejoué à chaque changement de modèle, de consigne ou de corpus ; il mesure les réponses justes et sourcées, les « je ne trouve pas » à raison, et la langue de la réponse.
