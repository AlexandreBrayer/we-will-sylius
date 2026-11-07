# Contexte Common : briques partagées par les fonctions intelligentes

## Rôle

Ce que plusieurs contextes utilisent pour leurs fonctions intelligentes (lot 2) : l'appel au modèle de langage, ses garde-fous, le nettoyage des photos. Aucune règle métier ici. Tous les contextes peuvent l'utiliser (ADR 008, contexte partagé).

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `LanguageModel` | `Ai/LanguageModelSelector` (alias) → `Mistral/MistralClient` ou `Anthropic/AnthropicClient` | Extraction structurée (texte, image, PDF) chez le fournisseur choisi par `YACK_AI_ADAPTER`, réponse forcée au schéma JSON demandé |

Autres classes :

- `Domain/AiInput` : une partie du message (texte, image JPEG ou PNG, PDF) ;
- `Domain/AiMode` : `YACK_AI_ADAPTER` (`fake` par défaut, `mistral`, `anthropic`) ; chaque contexte choisit son adapter avec ;
- `Domain/AiUnavailable`, `Domain/AiMisconfigured` : service indisponible (à transformer en message), configuration incomplète ;
- `Infrastructure/Anthropic/AiUsageGuard` : plafond quotidien d'appels réels (`YACK_AI_DAILY_CAP`) ;
- `Infrastructure/Image/ExifStripper` : réencodage GD, EXIF retiré, 1 600 px au plus.

## Interfaces exposées aux autres contextes

`LanguageModel` (et ses données `AiInput`, `AiMode`, exceptions), `ExifStripper`.

## Configuration

`.env` (valeurs vides, adapters factices) ; vraies valeurs dans `.env.local` : `YACK_AI_ADAPTER=mistral` (ou `anthropic`), `YACK_AI_MODEL`, `MISTRAL_API_KEY`, `MISTRAL_OCR_MODEL`, `MISTRAL_BASE_URL` (ou `ANTHROPIC_API_KEY`), `YACK_AI_DAILY_CAP`. Décision : `docs/adr/0003-llm-anthropic.md`.
