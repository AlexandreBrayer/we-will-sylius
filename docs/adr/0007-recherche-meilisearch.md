# ADR 0007 : recherche boutique Meilisearch, repli sur la base

- Statut : proposé (à valider par Thomas et Alexandre)
- Contexte : les installateurs cherchent par référence (souvent son début : `SRK25`), par abréviation métier (`pac`, `ecs`, `drv`) et avec des fautes. La recherche par nom de l'API catalogue Sylius ne trouve ni un début de référence, ni un synonyme, ni une faute, et ne classe pas par pertinence.

## Décision

- **Moteur** : Meilisearch (image `getmeili/meilisearch:v1.11`, service `meilisearch` de `compose.yml`, profil `search`), appelé par son API HTTP avec Symfony HttpClient, sans SDK.
- **Ports et adapters** (contexte Catalog, `src/Yack/Catalog/README.md`) : port `ProductSearch` avec deux adapters, Meilisearch et base de données (LIKE sur le nom français, le code, la marque, la gamme et la référence constructeur, synonymes compris) ; port `ProductSearchIndex` pour l'index ; `SearchDocumentSource` pour lire le catalogue.
- **Sélection et résilience** : `YACK_SEARCH_ENGINE` (`database` par défaut dans `.env`, la boutique tourne sans le service) ; avec `meilisearch`, toute défaillance (injoignable, une seconde dépassée, erreur, index absent) fait répondre la base et se journalise dans `var/log/yack_search.log`. Côté front, si l'API de recherche elle-même échoue, la page `/recherche` retombe sur la recherche par nom de l'API catalogue.
- **Aucun prix dans l'index** : la recherche renvoie des codes ; les cartes produit viennent de l'API catalogue, qui masque les prix selon le profil (règle d'Account).
- **Réglages** : champs cherchables par priorité (code, référence constructeur, nom, marque, gamme, typologie, chemin de catégorie, description courte) ; aucune faute tolérée sur les références ; un mot tapé comme une référence est d'abord cherché dans le code et la référence constructeur seulement ; règles de classement `words, typo, attribute, exactness, proximity, sort` (la correspondance sur la référence passe en tête) ; synonymes du métier dans `src/Yack/Catalog/Domain/SearchVocabulary.php`, partagés par les deux adapters ; tous les mots exigés, les derniers rendus facultatifs si aucun produit ne les a tous.
- **Indexation** : `app:yack:search:reindex` (`make yack-search-reindex`) reconstruit les index ; la synchronisation du catalogue met à jour les produits qu'elle applique.

## Conséquences

- Un changement fait dans le back-office Sylius n'atteint l'index qu'au `make yack-search-reindex` suivant (pas d'écouteur Doctrine).
- La base ne tolère pas les fautes de frappe et classe simplement (code exact, début de code, début de nom, puis nom).
- La page `/recherche` ne propose que l'ordre de pertinence.
- En production : clé d'API de recherche dédiée (pas la clé maître), `MEILI_ENV=production`, sauvegarde du volume ou reconstruction de l'index au déploiement.
