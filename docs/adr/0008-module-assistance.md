# ADR 0008 : module « Manuels et notices » (contexte Assistance)

- Statut : proposé (à valider par Aurélien, puis Thomas et Alexandre)
- Contexte : un installateur ou le SAV tape une référence, un modèle ou un n° de série (ou photographie la plaque), valide ce que le site a compris, et obtient les bons manuels officiels du modèle exact (sinon de la série ou du même type d'appareil, en le disant), avec une question possible sur les manuels dont le texte est indexé, réponse sourcée à la page. Les manuels viennent d'une recherche web des PDF publics des fabricants (MHI surtout, Yack, RHOSS). Ne pas confondre avec l'ADR 008 de rrg-api (dépendances entre contextes), que ce module applique.

## Décision

1. **Nouveau contexte `Assistance`** : back `src/Yack/Assistance/`, front `#lib/assistance/`, route `/assistance`. Retirable d'un bloc. Le contexte `Documentation` (`src/Yack/Documentation/`) porte une autre chose : l'assistant de la fiche produit sur ses notices, et à terme la base « Nos documentations » tirée de yack.fr. On ne le mélange pas avec les manuels des fabricants.
2. **Pas de Meilisearch** : la base des manuels est un index JSON versionné (dossier data/yack/assistance), lu en mémoire par l'adapter `JsonManualLibrary` du port `ManualLibrary`. Quelques centaines de documents et environ 300 produits se lisent en quelques millisecondes ; les règles de correspondance (même série, autre puissance, variante de suffixe) sont du code testable, qu'un moteur de recherche ne sait pas exprimer ; aucun service à lancer ni index à reconstruire. Seuil de réexamen : plus de 2 000 documents, texte intégral de tous les manuels ou recherche sémantique ; on branche alors un moteur derrière le port de récupération des passages de `Documentation`, sans toucher `Assistance`. Au-delà de 2 Mo d'index, mise en cache PHP dans `JsonManualLibrary`.
3. **La photo réutilise la lecture de plaque d'`AfterSales`** (port `NameplateReader`, adapters factice et réel) par une interface exposée dans son `Application`, et le composant de prise de photo de `/sav`, extrait en composant commun. Rien n'est refait ; les dépendances suivent l'ADR 008 de rrg-api (interfaces d'`Application` seulement).
4. **Écran de validation** entre la lecture et les résultats : la lecture seule ne cherche rien. Une référence mal lue ne mène jamais à un mauvais manuel sans que l'humain l'ait vue.
5. **Les questions aux manuels réutilisent le moteur de `Documentation`** (récupération lexicale BM25 de `RetrievePassages`, answerers factice et réel, `Answer::restrictedTo`) par une interface exposée dans son `Application`. `Assistance` garde ses textes et son API. Dépendance à sens unique : retirer `Assistance` ne touche pas `Documentation`.
6. **Les manuels s'ouvrent par lien vers le PDF officiel** (`url#page=N`) ; aucun PDF dans le dépôt (droits, et PDF non téléchargeables depuis l'environnement de développement).
7. **Interrupteur `YACK_ASSISTANCE`** (`1` actif, `0` coupé), lu par le back (API en 404) et par le front (route en 404, liens masqués) : masquer sans supprimer.
8. **Droits** : recherche et photo publiques (comme `/sav`) ; question aux manuels réservée aux comptes connectés (`ROLE_USER`, comme l'assistant de la fiche produit).
9. **Marque** : un champ `brand` facultatif s'ajoute à la lecture de plaque ; à défaut, la marque est déduite de la référence (`ReferenceBrand`) et affichée comme « déduite », jamais comme « lue ».
10. **Correspondance par niveaux** (exact, série, famille, aucun), règles dans `src/Yack/Assistance/README.md` : normalisation de la référence, découpage MHI (préfixe, puissance, série, suffixe), correction des confusions de lecture (`O`/`0`, `S`/`5`…) seulement quand le découpage échoue sans elle. Le n° de série ne sert jamais à deviner un modèle par son format ; seul le parc installé connu le relie à des modèles. Les niveaux famille et aucun ne nourrissent jamais une réponse aux questions.

## Conséquences

- La base se régénère hors du dépôt (générateur Python à partir des fichiers de la recherche) ; une base absente donne « aucun manuel », sans erreur.
- Un document invalide (id hors format, lien non https, aucun modèle couvert) arrête le chargement : la base est corrigée, pas filtrée en silence.
- La disponibilité des liens se contrôle hors de l'environnement de développement (le proxy bloque les PDF) ; un lien mort se corrige dans la base.
- Avant toute mise en production : accord de Yack (distributeur MHI) sur les extraits de texte des manuels indexés.
- Décisions laissées à Aurélien : garder ou non les documents non officiels (défaut : non) ; ouvrir la question aux visiteurs (défaut : non) ; libellé du menu (« Manuels et notices », proposé).
