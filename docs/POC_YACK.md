# POC Sylius — Yack (Clik&Yack)

Démonstrateur Sylius 2.2 / Symfony 7.4 pour Yack (génie climatique). C'est un monolithe Twig + Symfony UX/Stimulus, sans front headless.

## Installation

```bash
# Stack (si le port 80 est déjà pris sur la machine : HTTP_PORT=8088)
HTTP_PORT=8088 make up

# Base vierge, migrations, fixtures Yack, assets (et données ICU fr_FR)
HTTP_PORT=8088 make yack-reset

# Démo de la synchro PIM/ERP (flux delta simulé)
make yack-sync                      # data/yack/api_feed_update.json
make yack-sync FEED=data/yack/catalog.json
```

Commandes équivalentes : `bin/console doctrine:migrations:migrate -n && bin/console sylius:fixtures:load yack -n`.

> L'image `sylius-php:8.3-alpine` ne contient que les données ICU anglaises : sans `make yack-icu` (inclus dans `yack-reset`), les prix s'affichent au format `€6,490.00`. Il faut relancer cette commande après chaque recréation du conteneur.
> Le listener Meilisearch (travail en cours, hors POC) exige que le conteneur `meilisearch` tourne pendant le chargement des fixtures.

Boutique : http://localhost:8088/fr_FR/ — Admin : http://localhost:8088/admin/

## Comptes

| Profil | Identifiant | Mot de passe | Prix | Panier / commande |
|---|---|---|---|---|
| Visiteur | — | — | masqués (« Connectez-vous… ») | non (panier → login) |
| PRO (groupe client `pro`) | pro@yack.test | yack | masqués (« tarifs négociés, contactez votre agence ») | non (403) |
| ECOM (groupe client `ecom`) | ecom@yack.test | yack | visibles (HT) | oui |
| Admin | admin@yack.test | yack | — | — |

Règle métier : un client **sans groupe** est considéré ECOM (inscription libre sur le site). Seul Yack crée les comptes PRO (back-office → Clients → groupe `pro`).

## Numéros de série de démonstration

| N° de série | Modèles (ServiceCode) | Mise en service | Garantie (au 02/11/2026) |
|---|---|---|---|
| YK25EVO08M001234 | SC-EVO-M08 (Evolys 8 kW, 2 vues éclatées) | 10/04/2025 | pièces + compresseur sous garantie |
| YK19KAZ35S004567 | SC-KAZ-I35 + SC-KAZ-O35 (Kaze intérieure + extérieure) | 03/06/2019 | hors garantie |
| YK24HYD11S000321 | SC-HYD-I11 + SC-HYD-O11 (Hydra split) | 21/02/2024 | pièces hors garantie, compresseur sous garantie |
| YK22THD200000789 | SC-THD-200 (Aquaflow 200 L) | 08/11/2022 | compresseur sous garantie |

La saisie tolère la casse, les espaces, `-`, `_` et `.` (`yk19-kaz35s-004567` fonctionne). Un ServiceCode (`SC-KAZ-O35`) est aussi accepté.

## Script de démo (≈ 15 min)

1. **Accueil en visiteur** : logo Clik&Yack, menu *Nos produits → Clik&Yack / Clik&Yack Parts*, *SAV & pièces détachées*, *Trouver un installateur*. Badge « Visiteur » dans le bandeau.
2. **Nos produits → Clik&Yack** : catalogue de produits finis (PAC air/eau, air/air, climatisation, eau chaude sanitaire). Aucun prix n'apparaît ; chaque carte affiche « Connectez-vous pour voir les prix ». Ouvrir une fiche, par exemple *Evolys 8 kW* : onglet attributs (Puissance, SCOP, fluide, gamme, marque), pas de bouton panier.
3. **Nos produits → Clik&Yack Parts** : les 41 pièces détachées, classées dans 8 sous-familles.
4. **Connexion pro@yack.test / yack** : badge « Compte PRO ». Les prix restent masqués, avec le message « tarifs négociés ». Pas d'icône panier. `/fr_FR/cart/` renvoie 403.
5. **Déconnexion → connexion ecom@yack.test / yack** : badge « Compte ECOM ». Les prix HT et l'icône panier apparaissent. Sur *Evolys 8 kW*, cliquer *Ajouter au panier*.
6. **SAV** : saisir `YK24HYD11S000321`. La garantie s'affiche (« Simulation API garantie Yack »), ainsi que les 2 modèles. Ouvrir *module hydraulique intérieur* puis la vue éclatée : au survol, les repères numérotés et les lignes du tableau se mettent en surbrillance. Le tableau donne le prix et le stock. *Ajouter* la pièce n°5 : elle arrive **dans le même panier** que la PAC.
7. **Panier** : la PAC et la pièce figurent dans une seule commande. Le tunnel Sylius standard (adresse → livraison → paiement par virement) est disponible.
8. **Synchro PIM/ERP** (terminal) : `make yack-sync`. Résultat : 2 créés (Evolys 18 kW, Module Wi-Fi Kaze), 2 mis à jour (Evolys 8 kW passe à 6 990 €, compresseur Evolys en rupture), 1 désactivé (climatiseur mobile 2,6 kW), 2 rejets (prix négatif, marque inconnue « Frigotech »), code retour 1. Montrer `var/log/yack_sync.log` (JSON, un événement par produit), puis rafraîchir la boutique : nouveau prix, compresseur « Sur commande » dans la vue éclatée, mobile 2,6 kW en 404.
9. **Trouver un installateur** : filtres secteur / gamme / activité (ex. *Sud-Est + PAC air/eau + Dépannage* → 2 résultats) et carte Leaflet.
10. **Back-office** (admin@yack.test) : produits, groupes clients, attribution d'un client au groupe PRO, données natives Sylius.

## Correspondance écrans ↔ cahier des charges

| Écran / démonstration | Section CDC couverte |
|---|---|
| Menu *Nos produits → Clik&Yack / Clik&Yack Parts*, taxonomie multi-niveaux, attributs techniques, marques | Catalogue / navigation produits finis et pièces détachées |
| Profils Visiteur / PRO / ECOM (prix et panier) | Comptes clients, profils PRO vs e-commerce, visibilité des prix |
| SAV : n° de série → modèles (ServiceCode) → vues éclatées → pièces (prix, stock, ajout panier) | Parcours SAV / pièces détachées |
| Bloc garantie sur la page SAV | Consultation de la garantie (API Yack) |
| `app:yack:catalog:sync`, log `yack_sync` | Interface PIM/ERP (Yack maître de la donnée produit, prix, stock) |
| Trouver un installateur | Annuaire installateurs (secteur / gamme / activité) |
| Habillage Clik&Yack | Charte (habillage léger, sans travail de design) |

*Les numéros de section du CDC ne figuraient pas dans le brief : correspondance à compléter par la production.*

## Architecture (hexagonale, `src/Yack/<Contexte>/{Domain,Application,Infrastructure}`)

- **Account**
  - `CustomerProfile` (Visitor / Pro / Ecom) et `CommercePermission` (`YACK_SEE_PRICES`, `YACK_PLACE_ORDER`) portent la règle métier.
  - `CommercePermissionVoter` l'expose à la sécurité Symfony.
  - Les blocs Twig hooks déclarent la permission requise (`configuration.yack_permission` dans `config/packages/yack.yaml`) ; `PermissionAwareHookableRenderer` les filtre. Aucun template Sylius n'est surchargé pour masquer les prix.
  - Contrôle côté serveur : `CommerceAccessListener` (événement `sylius.cart_item_add` et routes panier / tunnel de commande).
- **Catalog**
  - Port `ProductSource`, adapter `JsonFileProductSource` (format proche d'une API Yack).
  - Port `ProductCatalog`, adapter `SyliusProductCatalog`.
  - Message `SynchronizeCatalog` traité par `SynchronizeCatalogHandler` (Messenger, bus `sylius.command_bus`), appelé par la commande `app:yack:catalog:sync`.
  - La fixture `yack_catalog` utilise **le même pipeline** pour le chargement initial.
- **AfterSales**
  - Entités Doctrine `src/Entity/AfterSales` (`Model`, `ExplodedView`, `ExplodedViewItem`, `SerialNumber`) ; migration `Version20261102094834`.
  - Port `AfterSalesCatalog` (adapter Doctrine) et port `WarrantyProvider` (adapter `FakeWarrantyProvider`).
  - L'ajout au panier passe par l'événement Sylius standard (`SyliusPartCartAdder`).
- **Installer** : port `InstallerDirectory`, adapter `InMemoryInstallerDirectory`, carte Leaflet chargée depuis le CDN unpkg via un contrôleur Stimulus.

## Ce qui est réel / ce qui est simulé

| Réel (code de production réutilisable) | Simulé (adapters POC à remplacer) |
|---|---|
| Règle PRO / ECOM (voter, hooks, contrôle panier / commande côté serveur) | `JsonFileProductSource` lit des fichiers JSON (`data/yack/*.json`) au lieu de l'API PIM/ERP Yack |
| Pipeline de synchro (création, mise à jour, désactivation, rejets, logs JSON, code retour) | `FakeWarrantyProvider` : dates déterministes par n° de série (garantie pièces 2 ans, compresseur 5 ans) |
| Modèle SAV (entités, migration, repository), parcours et panier unique | Vues éclatées : schémas SVG dessinés pour la démo (`public/yack/sav`) ; images produits générées |
| Panier, tunnel de commande, back-office : Sylius natif | `InMemoryInstallerDirectory` : 16 installateurs fictifs |
| | Données catalogue, marques (Yack, Aerys, Hydrolia, Calyos), prix et stocks fictifs |

Pour brancher les vraies API Yack, il suffit d'écrire un nouvel adapter (client HTTP) pour `ProductSource`, `WarrantyProvider` et `InstallerDirectory`. Les domaines, contrôleurs et templates restent inchangés.

## Limites connues

- La page `/fr_FR/search` (Meilisearch, travail en cours hors POC) affiche encore les prix à tous les profils. Il faut y appliquer `is_granted('YACK_SEE_PRICES')`. Son index n'est pas purgé par `yack-reset` (`make meilisearch-index`).
- Les endpoints API Platform de Sylius (`/api/v2/shop/...`) ne sont pas restreints par profil. Le POC ne couvre que le front Twig.
- Les prix affichés sont HT (taxe à 20 % appliquée au panier). Il n'existe pas de grille tarifaire spécifique PRO : les prix PRO restent hors ligne.
- Le fil d'Ariane produit affiche la racine « Menu » (comportement natif Sylius).
- PHPStan au niveau 9 du dépôt remonte encore des casts `mixed` dans le mapping JSON et dans les fixtures, ainsi que des faux positifs Doctrine sur les `$id`.
