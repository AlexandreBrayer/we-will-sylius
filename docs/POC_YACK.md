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

Boutique : http://localhost:8088/fr_FR/ — Admin : http://localhost:8088/admin/ — **E-mails (MailHog) : http://localhost:8025** (tous les e-mails Sylius/Yack y arrivent : `MAILER_DSN=smtp://mailhog:1025`, voir `compose.override.yml`).

## Comptes

| Profil | Identifiant | Mot de passe | Prix | Panier / commande |
|---|---|---|---|---|
| Visiteur | — | — | masqués (« Connectez-vous… ») | non (panier → login) |
| PRO (groupe client `pro`) | pro@yack.test, pro2@yack.test | yack | masqués, remplacés par l'incitation « Commander en ligne : demandez l'ouverture de votre compte à la commande » (ou « Demande en cours ») | non (403) |
| ECOM (groupe client `ecom`) | ecom@yack.test | yack | visibles (HT) | oui |
| Admin | admin@yack.test | yack | — | — |

Règle métier : l'inscription Yack attribue le groupe `pro` ou `ecom` selon le profil choisi ; un client **sans groupe** (invité, compte natif) est considéré ECOM.

**Inscription en 4 étapes** (`/fr_FR/inscription`, CDC §3.4 — `App\Yack\Account\Application\Registration`)
- La route garde le nom Sylius `sylius_shop_register` : tous les liens « Créer un compte » y mènent ; l'ancienne URL `/fr_FR/register` redirige (301).
- Étapes avec indicateur de progression, données gardées en session (mot de passe stocké **haché** uniquement), impossible de sauter une étape :
  1. **Profil** : PRO (installateur) ou ECOM.
  2. **Société** : raison sociale + SIRET obligatoires en PRO (facultatifs en ECOM), SIRET contrôlé (14 chiffres + clé de Luhn, `Domain\Siret`), n° de TVA intracom. facultatif (format FR, clé et SIREN cohérents avec le SIRET).
  3. **Contact** : nom, prénom, e-mail (unicité vérifiée), téléphone, mot de passe (8 caractères min., confirmation).
  4. **Adresses** : facturation (= adresse par défaut) + livraison identique ou autre (ajoutée au carnet d'adresses).
- Messages d'erreur en français à chaque étape. SIRET valides pour tester : `73282932000074` (TVA `FR44732829320`), `81234567800013`.
- Le compte est créé **inactif** (canal en `account_verification_required: true`) : e-mail Yack « Activez votre compte Clik&Yack », le lien active le compte puis renvoie vers la connexion. Avant activation, la connexion échoue (« Identifiants invalides », comportement Symfony) ; la page de connexion le rappelle.
- Mot de passe oublié : flux Sylius natif, e-mail Yack « Réinitialisation de votre mot de passe Clik&Yack ».
- Connexion et mot de passe oublié : encart Yack « Nouveau sur Clik&Yack ? » ; tableau de bord du compte : profil, société, SIRET, TVA, adresse de facturation, accès commande (bouton de demande ou « demande en cours ») et raccourcis (commandes, adresses, SAV).
- La nouvelle inscription PRO apparaît immédiatement dans le bloc « Nouvelles inscriptions PRO » du tableau de bord back-office.

**Demande d'ouverture à la commande (PRO → ECOM)**
- Le formulaire `/fr_FR/compte/acces-commande` est pré-rempli depuis le compte : société et SIRET (champs ajoutés au client), nom, e-mail, téléphone. Le client complète l'adresse et un message libre.
- La demande est enregistrée (`yack_order_access_request`) avec un statut : en attente, acceptée ou refusée.
- Le back-office la présente dans *Clients → Demandes accès commande* (liste filtrable + détail).
- **Accepter** fait passer le client dans le groupe `ecom` : prix et panier sont visibles dès la page suivante. Le client reçoit l'e-mail Yack **« Votre compte est ouvert à la commande »** (code `yack_order_access_granted`, port `OrderAccessNotifier`).
- La décision se prend depuis la liste des demandes **ou directement depuis la fiche client** (*Clients → [client]* : société, SIRET, TVA intracom, profil, historique des demandes avec boutons Accepter / Refuser).
- **Refuser** le laisse en PRO ; il peut alors redéposer une demande.
- Étape manuelle équivalente : *Clients → [client] → Modifier → Groupe = ECOM*.
- `pro2@yack.test` (Thermi-Sud Énergies) reste disponible pour rejouer la démo, puisque `pro@` passe ECOM une fois sa demande acceptée. On peut aussi créer un nouveau compte PRO par l'inscription.

**Moyens de paiement et escompte**

| Moyen de paiement (code) | Escompte |
|---|---|
| Virement sous 15 jours (`virement_15j`) | 2 % du HT |
| Carte bancaire (`carte_bancaire`) | 2 % du HT — POC : passerelle *offline*, aucun débit réel |
| Virement à échéance (`virement_echeance`) | aucun (prix normal) |

- **Configuration** : paramètre `app.yack.payment_method_escompte_rates` dans `config/packages/yack.yaml`. Les taux ne sont pas écrits en dur dans les templates.
- **Calcul** : `App\OrderProcessing\EscompteProcessor` (priorité 15 : après les promotions, **avant les taxes**) répartit l'escompte sur les unités de commande (ajustements de type `escompte`, base = articles HT, hors port).
  - Sylius taxe chaque article sur son total après ajustements : **la TVA est donc calculée sur le HT escompté**.
  - Le taux du moyen de paiement s'applique dès l'arrivée à l'étape paiement (moyen présélectionné), puis est recalculé à chaque changement de moyen.
  - Un moyen absent de la liste retombe sur l'escompte propre au client.
- **Affichage** : ligne « Escompte 2 % (sur le HT) » dans le panier, le résumé du tunnel, le récapitulatif, la confirmation, l'e-mail, le détail de commande du compte client, la liste et la fiche commande en back-office.
- **Totaux vérifiés dans le navigateur** (Evolys 8 kW, 7 290 € HT, livraison transporteur 19,90 € non taxée, TVA 20 %) :

| Moyen | Escompte | TVA | Total TTC |
|---|---|---|---|
| Virement sous 15 jours | −145,80 € | 1 428,84 € | **8 592,94 €** |
| Carte bancaire | −145,80 € | 1 428,84 € | **8 592,94 €** |
| Virement à échéance | — | 1 458,00 € | **8 767,90 €** |

- `app:escompte:expire` (escompte non honoré dans les délais) retire les ajustements mais ne recalcule pas la TVA de la commande passée (limite connue).

**Confirmation de commande et paiement** (`App\Yack\Payment`)
- Tunnel en français avec indicateur de progression *Adresse › Livraison › Paiement › Récapitulatif › Confirmation*.
- **Virement** (15 jours ou échéance J+30) : la page de confirmation, l'e-mail et le détail de commande affichent un **RIB fictif** (paramètre `app.yack.payment_terms.bank_account`), la référence à indiquer `YACK-<n° de commande>` et la date limite.
- **Carte** : « Paiement accepté » ; `FakeCardPaymentListener` passe le paiement à *payé* à la validation (aucun PSP).
- E-mails Yack (gabarits `templates/shop/yack/email/`, configurés via `sylius_mailer` dans `yack.yaml`) : confirmation de commande (avec ligne d'escompte, TVA, RIB), expédition (n° de suivi), activation de compte, bienvenue, mot de passe oublié, compte ouvert à la commande.
- Historique client : date FR, moyen de paiement + statut (« Virement attendu », « Payée »), statut de livraison ; pas de bouton « Payer ». Détail : moyen de paiement, modalités de virement (ou « Virement reçu »), n° de suivi.

**Back-office**
- Habillage léger : logo Yack et accent magenta (`assets/admin/styles/yack.scss`), interface admin en français (`locale_code: fr_FR`).
- Tableau de bord : nouvelles commandes (natif), compteur des **demandes d'ouverture à la commande en attente** (actions en attente), **nouvelles inscriptions PRO**.
- Clients : colonnes Profil / Société / SIRET, filtre **Profil PRO / ECOM**, recherche par société ou SIRET.
- Commandes : colonne **Paiement / escompte**.
- Paiement virement reçu : fiche commande → *Paiements* → **Terminer** (transition Sylius `complete`). Expédition : saisir le n° de suivi → **Livrer** (transition `ship`, e-mail d'expédition). Les deux sont visibles côté client.

## Numéros de série de démonstration

| N° de série | Modèles (ServiceCode) | Mise en service | Garantie (au 02/11/2026) |
|---|---|---|---|
| YK25EVO08M001234 | SC-EVO-M08 (Evolys 8 kW, 2 vues éclatées) | 10/04/2025 | pièces + compresseur sous garantie |
| YK19KAZ35S004567 | SC-KAZ-I35 + SC-KAZ-O35 (Kaze intérieure + extérieure) | 03/06/2019 | hors garantie |
| YK24HYD11S000321 | SC-HYD-I11 + SC-HYD-O11 (Hydra split) | 21/02/2024 | pièces hors garantie, compresseur sous garantie |
| YK22THD200000789 | SC-THD-200 (Aquaflow 200 L) | 08/11/2022 | compresseur sous garantie |

La saisie tolère la casse, les espaces, `-`, `_` et `.` (`yk19-kaz35s-004567` fonctionne). Un ServiceCode (`SC-KAZ-O35`) est aussi accepté.

## Script de démo (≈ 15 min)

1. **Accueil en visiteur** : en-tête calqué sur le site Yack.
   - Logo Yack, champ *Rechercher* (recherche catalogue Sylius native sur *Nos produits*).
   - Menu : *Le groupe*, *Les marques*, *Nos services* (SAV & pièces détachées, Trouver un installateur), *Nos produits → Clik&Yack / Clik&Yack Parts*, *Nos documentations*, et à droite *Suivez vos commandes*.
   - Badge « Visiteur » dans le bandeau.
   - *Le groupe*, *Les marques* et *Nos documentations* sont des rubriques éditoriales non cliquables, hors périmètre du POC.
2. **Nos produits → Clik&Yack** : catalogue de produits finis (PAC air/eau, air/air, climatisation, eau chaude sanitaire). Aucun prix n'apparaît ; chaque carte affiche « Connectez-vous pour voir les prix ». Ouvrir une fiche, par exemple *Evolys 8 kW* : onglet attributs (Puissance, SCOP, fluide, gamme, marque), pas de bouton panier.
3. **Nos produits → Clik&Yack Parts** : les 41 pièces détachées, classées dans 8 sous-familles.
4. **Inscription PRO** (`Créer un compte`) : profil PRO → société (montrer l'erreur sur un SIRET faux, puis `73282932000074` / `FR44732829320`) → contact → adresses (livraison « autre adresse »). Page « Votre compte est créé ».
   - MailHog (http://localhost:8025) : ouvrir « Activez votre compte Clik&Yack » et cliquer *Activer mon compte* → « Votre adresse email a été vérifiée ».
   - Se connecter : badge « Compte PRO », tableau de bord avec la société et le bouton **Demander l'ouverture à la commande**. Prix masqués, `/fr_FR/cart/` renvoie 403.
   - Envoyer la demande (formulaire pré-rempli : société, SIRET, téléphone, adresse). Fiches et listes indiquent « Demande d'ouverture à la commande en cours ».
   - **Back-office** (admin@yack.test, autre navigateur) : le tableau de bord montre la nouvelle inscription PRO et 1 demande en attente. Ouvrir la **fiche client** → **Accepter**. MailHog : « Votre compte est ouvert à la commande ».
   - Recharger la boutique : le badge passe à « Compte ECOM », prix et panier apparaissent. (Variante sans inscription : pro@yack.test / yack.)
5. **Déconnexion → connexion ecom@yack.test / yack** : badge « Compte ECOM ». Les prix HT et l'icône panier apparaissent. Sur *Evolys 8 kW*, cliquer *Ajouter au panier*.
6. **SAV** : saisir `YK24HYD11S000321`. La garantie s'affiche (« Simulation API garantie Yack »), ainsi que les 2 modèles. Ouvrir *module hydraulique intérieur* puis la vue éclatée : au survol, les repères numérotés et les lignes du tableau se mettent en surbrillance. Le tableau donne le prix et le stock. *Ajouter* la pièce n°5 : elle arrive **dans le même panier** que la PAC.
7. **Panier et paiement** : la PAC et la pièce figurent dans une seule commande. Tunnel en français avec indicateur *Adresse › Livraison › Paiement › Récapitulatif › Confirmation* ; à l'étape paiement, les 3 moyens affichent leur badge (« Escompte 2 % » / « Prix normal »).
   - Choisir *Virement sous 15 jours* : la ligne « Escompte 2 % (sur le HT) » apparaît, TVA recalculée.
   - Commander : la confirmation affiche le **RIB / IBAN**, la référence `YACK-<n°>` et la date limite ; e-mail « Confirmation de votre commande » dans MailHog.
   - Back-office : commande → *Paiements* → **Terminer** (virement reçu), puis n° de suivi → **Livrer** : e-mail « Votre commande … a été expédiée ».
   - *Mon compte → Historique des commandes* : « Payée », « Expédiée » ; le détail affiche « Virement reçu », le n° de suivi et la ligne d'escompte.
   - Exemple vérifié (carte inverter Kaze 289 € HT + Evolys 8 kW 7 290 € HT, virement 15 jours) : total articles 7 579,00 €, escompte −151,58 €, livraison 19,90 €, TVA 1 485,48 €, **total TTC 8 932,80 €**.
8. **Synchro PIM/ERP** (terminal) : `make yack-sync`. Résultat : 2 créés (Evolys 18 kW, Module Wi-Fi Kaze), 2 mis à jour (Evolys 8 kW passe à 6 990 €, compresseur Evolys en rupture), 1 désactivé (climatiseur mobile 2,6 kW), 2 rejets (prix négatif, marque inconnue « Frigotech »), code retour 1. Montrer `var/log/yack_sync.log` (JSON, un événement par produit), puis rafraîchir la boutique : nouveau prix, compresseur « Sur commande » dans la vue éclatée, mobile 2,6 kW en 404.
9. **Trouver un installateur** : filtres secteur / gamme / activité (ex. *Sud-Est + PAC air/eau + Dépannage* → 2 résultats) et carte Leaflet.
10. **Back-office** (admin@yack.test) : produits, groupes clients, attribution d'un client au groupe PRO, données natives Sylius.

## Correspondance écrans ↔ cahier des charges

| Écran / démonstration | Section CDC couverte |
|---|---|
| Menu *Nos produits → Clik&Yack / Clik&Yack Parts*, taxonomie multi-niveaux, attributs techniques, marques | Catalogue / navigation produits finis et pièces détachées |
| Profils Visiteur / PRO / ECOM (prix et panier) | Comptes clients, profils PRO vs e-commerce, visibilité des prix |
| Inscription 4 étapes (profil, société + SIRET, contact, adresses), activation par e-mail, mot de passe oublié, tableau de bord du compte | Création de compte et espace client (§3.4) |
| Encart « Commander en ligne » PRO, formulaire de demande, BO *Demandes accès commande* et fiche client | Ouverture de compte à la commande pour les installateurs (workflow en attente / acceptée / refusée) |
| Étape paiement : 3 moyens + ligne « Escompte 2 % » (tunnel, confirmation, compte, BO) | Moyens de paiement, escompte pour paiement anticipé |
| SAV : n° de série → modèles (ServiceCode) → vues éclatées → pièces (prix, stock, ajout panier) | Parcours SAV / pièces détachées |
| Bloc garantie sur la page SAV | Consultation de la garantie (API Yack) |
| `app:yack:catalog:sync`, log `yack_sync` | Interface PIM/ERP (Yack maître de la donnée produit, prix, stock) |
| Trouver un installateur | Annuaire installateurs (secteur / gamme / activité) |
| Habillage Yack : magenta #A7357F, CTA noirs, typo système, en-tête logo + recherche + menu 2 lignes (`assets/shop/styles/yack.scss`, `templates/shop/yack/layout/`) | Charte (passe rapide, sans refonte page à page) |

*Les numéros de section du CDC ne figuraient pas dans le brief : correspondance à compléter par la production.*

## Architecture (hexagonale, `src/Yack/<Contexte>/{Domain,Application,Infrastructure}`)

- **Account**
  - `CustomerProfile` (Visitor / Pro / Ecom) et `CommercePermission` (`YACK_SEE_PRICES`, `YACK_PLACE_ORDER`) portent la règle métier ; `Siret` valide SIRET et n° de TVA.
  - Inscription : `Application\Registration\{RegistrationStep, RegistrationData, RegisterCustomer}` ; `RegisterCustomer` crée client + utilisateur inactif + adresses et déclenche `sylius.customer.post_register` (e-mail d'activation Sylius). Formulaires par étape dans `Infrastructure/Form/Registration`, contrainte `ValidSiret`, contrôleur `RegistrationController`.
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
| Escompte par moyen de paiement : ajustement réel sur les unités, TVA recalculée sur le HT escompté, taux configurables | Carte bancaire : `FakeCardPaymentListener` marque le paiement payé (aucun PSP branché, aucun débit) |
| Demandes d'accès commande : entité, workflow, passage en groupe ECOM, e-mail au client à l'acceptation ; inscription (contrôle SIRET / TVA, activation e-mail) | RIB fictif (Banque Fictive du Sud) sur confirmation / e-mail ; SIRET contrôlé par sa clé uniquement (pas d'appel à l'API Sirene) ; pas de notification à Yack lors d'une nouvelle demande ; e-mails capturés par MailHog |
| | Données catalogue, marques (Yack, Aerys, Hydrolia, Calyos), prix et stocks fictifs |

Pour brancher les vraies API Yack, il suffit d'écrire un nouvel adapter (client HTTP) pour `ProductSource`, `WarrantyProvider` et `InstallerDirectory`. Les domaines, contrôleurs et templates restent inchangés.

## Limites connues

- La page `/fr_FR/search` (Meilisearch, travail en cours hors POC) affiche encore les prix à tous les profils. Il faut y appliquer `is_granted('YACK_SEE_PRICES')`. Son index n'est pas purgé par `yack-reset` (`make meilisearch-index`).
- Les endpoints API Platform de Sylius (`/api/v2/shop/...`) ne sont pas restreints par profil. Le POC ne couvre que le front Twig.
- Les prix affichés sont HT (taxe à 20 % appliquée au panier). Il n'existe pas de grille tarifaire spécifique PRO : les prix PRO restent hors ligne.
- Le fil d'Ariane produit affiche la racine « Menu » (comportement natif Sylius).
- PHPStan au niveau 9 du dépôt remonte encore des casts `mixed` dans le mapping JSON et dans les fixtures du catalogue (`src/Yack/Catalog`).
- Le plugin Mollie reste installé : ses colonnes « Subscription » et la ligne « Frais de paiement » sont masquées côté boutique, mais le menu admin « Subscriptions » et la légende du graphique du tableau de bord restent en anglais. Les liens du pied de page boutique (About, FAQ…) sont en anglais.
- Le total « Total » du résumé de commande en back-office est l'`itemsTotal` natif (TTC, escompte déduit) ; le total TTC Yack est la ligne « Total TTC ».
- Le changement de moyen de paiement à l'étape paiement ne met à jour le résumé qu'après « Suivant » (pas de recalcul en direct).
