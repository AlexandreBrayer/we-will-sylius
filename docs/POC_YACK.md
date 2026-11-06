# POC Sylius — Yack (Clik&Yack)

Démonstrateur Sylius 2.2 / Symfony 7.4 pour Yack (génie climatique), en architecture **headless** :

- la boutique et le builder CMS sont une application **SvelteKit** (`front/`, Svelte 5, TypeScript strict, adapter-node), voir `front/README.md` ;
- **Sylius** ne sert plus que le back-office (`/admin`), l'API (`/api/v2`), les médias, les assets et les e-mails. L'ancienne boutique Twig est supprimée ;
- le contrat d'API consommé par le front est décrit dans `docs/API_FRONT.md`.

## Architecture

```mermaid
flowchart LR
    B[Navigateur] -->|:8088| N[nginx]
    N -->|/admin, /api, /media, /bundles, /build,<br/>/_profiler, fichiers de public/| P[PHP-FPM<br/>Sylius 2.2]
    N -->|tout le reste| F[front:3000<br/>SvelteKit SSR + BFF]
    F -->|http://nginx/api/v2<br/>JWT shop / admin| N
    P --> DB[(MySQL)]
    P -->|SMTP :1025| M[MailHog :8025]
    B -.->|lecture des e-mails| M
    A[Admin Yack] -->|/admin| N
```

- Le navigateur ne parle qu'à nginx. Le front SvelteKit est un **BFF** : il appelle l'API Sylius côté serveur et garde les JWT dans des cookies `httpOnly` (`yack_session`, `yack_cart`, `yack_cms_token`). Aucun jeton n'est exposé au JavaScript, pas de CORS.
- Les règles métier restent dans Sylius : prix `null` et panier / commandes en 401 / 403 pour les visiteurs et les PRO (`CommerceAccessListener`, voir `docs/API_FRONT.md` §« Profils »), escompte, workflows, e-mails.
- Le back-office Sylius reste en Twig (grilles et hooks Yack : escompte, demandes d'ouverture, inscriptions PRO, menu *Contenu → Pages*).
- Les anciennes URL de la boutique Twig (`/fr_FR/…`) répondent par une **redirection 301** vers la route SvelteKit équivalente : accueil (nginx, un seul saut), fiches produit, catégories, panier, connexion, inscription, liens d'activation et de mot de passe, compte, SAV, installateurs (`front/src/lib/server/legacy.ts`). Une ancienne URL sans équivalent répond 404.

## Installation

```bash
make yack-reset                 # base vierge, migrations, fixtures Yack, clés JWT, ICU fr_FR, assets, npm ci du front, MailHog vidé
make yack-up                    # socle + nginx + front (vite dev) sur http://localhost:8088, attend que la boutique réponde
FRONT_MODE=prod make yack-up    # idem avec le build de production du front (npm run build && node build)

# Démo de la synchro PIM/ERP (flux delta simulé)
make yack-sync                       # data/yack/api_feed_update.json
make yack-sync FEED=data/yack/catalog.json
```

- Port : `YACK_HTTP_PORT` (8088 par défaut, le port 80 étant souvent pris). `yack-reset` copie `compose.override.dist.yml` en `compose.override.yml` s'il manque (MailHog).
- `yack-reset` ne supprime pas les volumes Docker (médias, données Meilisearch du travail en cours hors POC) ; il recharge la base et les fixtures.
- L'image `sylius-php:8.3-alpine` ne contient que les données ICU anglaises : `make yack-icu` (appelé par `yack-reset` et `yack-up`) installe celles du français pour le formatage des montants dans le back-office et les e-mails.
- Le listener Meilisearch (travail en cours, hors POC) exige que le conteneur `meilisearch` tourne pendant le chargement des fixtures.

| Service | URL |
|---|---|
| Boutique (SvelteKit) | http://localhost:8088/ |
| Back-office Sylius | http://localhost:8088/admin/ |
| Builder CMS | http://localhost:8088/cms-admin/builder |
| Pages CMS publiques | http://localhost:8088/&lt;slug&gt; |
| E-mails (MailHog) | http://localhost:8025 |

## Comptes

Mot de passe `yack` pour tous les comptes de fixtures.

| Profil | Identifiant | Prix | Panier / commande |
|---|---|---|---|
| Visiteur | — | masqués (« Connectez-vous pour voir les prix ») | non |
| PRO (groupe `pro`) | pro@yack.test, pro2@yack.test | masqués, incitation « Demander l'ouverture à la commande » (ou « Demande en cours ») | non (API 403) |
| ECOM (groupe `ecom`) | ecom@yack.test | visibles (HT) | oui |
| Admin (back-office + builder, `api: true`) | admin@yack.test | — | — |

Règle métier : l'inscription attribue le groupe `pro` ou `ecom` selon le profil choisi ; un client **sans groupe** est considéré ECOM. SIRET valides pour tester : `73282932000074` (TVA `FR44732829320`), `81234567800013`.

Après `make yack-reset`, la base ne contient **aucune commande** (numérotation à partir de `000000001`, rien de pré-payé ni de pré-expédié), 3 clients (pro, pro2, ecom) et 2 pages CMS publiées (*Accueil*, *Nos services*).

## Numéros de série de démonstration

| N° de série | Modèles (ServiceCode) | Mise en service | Garantie (au 02/11/2026) |
|---|---|---|---|
| YK25EVO08M001234 | SC-EVO-M08 (Evolys 8 kW, 2 vues éclatées) | 10/04/2025 | pièces + compresseur sous garantie |
| YK19KAZ35S004567 | SC-KAZ-I35 + SC-KAZ-O35 (Kaze intérieure + extérieure) | 03/06/2019 | hors garantie |
| YK24HYD11S000321 | SC-HYD-I11 + SC-HYD-O11 (Hydra split) | 21/02/2024 | pièces hors garantie, compresseur sous garantie |
| YK22THD200000789 | SC-THD-200 (Aquaflow 200 L) | 08/11/2022 | compresseur sous garantie |

La saisie tolère la casse, les espaces, `-`, `_` et `.` (`yk19-kaz35s-004567` fonctionne). Un ServiceCode (`SC-KAZ-O35`) est aussi accepté.

## Moyens de paiement et escompte

| Moyen de paiement (code) | Escompte | Modalités |
|---|---|---|
| Virement sous 15 jours (`virement_15j`) | 2 % du HT | RIB fictif, référence `YACK-<n°>`, échéance J+15 |
| Carte bancaire (`carte_bancaire`) | 2 % du HT | passerelle *offline* : paiement marqué payé, aucun débit |
| Virement à échéance (`virement_echeance`) | aucun (prix normal) | RIB fictif, échéance J+30 |

- Taux : paramètre `app.yack.payment_method_escompte_rates` (`config/packages/yack.yaml`).
- `App\OrderProcessing\EscompteProcessor` (après les promotions, **avant les taxes**) répartit l'escompte sur les unités (ajustements `escompte`, base = articles HT hors port) : la **TVA est calculée sur le HT escompté**. La livraison n'est pas taxée dans les fixtures.
- Affichage : panier, tunnel, confirmation, e-mail, compte client, liste et fiche commande du back-office.

Totaux observés dans le navigateur (démo du 06/11/2026, après `make yack-reset`) :

| Commande | Moyen | Sous-total HT | Escompte | Port HT | TVA | Total TTC |
|---|---|---|---|---|---|---|
| Evolys 8 kW (7 290 €) + résistance Hydra n°5 (139 €) | Virement 15 j | 7 429,00 € | −148,58 € | 19,90 € | 1 456,08 € | **8 756,40 €** |
| 2 × anode titane (69 €) | Carte bancaire | 138,00 € | −2,76 € | 19,90 € | 27,05 € | **182,19 €** |
| Evolys 8 kW | Virement à échéance | 7 290,00 € | — | 19,90 € | 1 458,00 € | **8 767,90 €** |

Référence (session précédente) : Evolys 8 kW seule en virement 15 j → escompte −145,80 €, TVA 1 428,84 €, **8 592,94 €** TTC.

## Script de démo (≈ 20 min)

Captures de référence : `/tmp/yack-demo/final-*.png` (régénérées à chaque passage).

1. **Accueil en visiteur** (http://localhost:8088/) : page CMS *Accueil* rendue en SSR (bandeau, gammes, incontournables, SAV, appel PRO), derniers produits, badge « Visiteur ». Menu : *Le groupe*, *Les marques*, *Nos documentations* (rubriques non cliquables, hors périmètre), *Nos services* (SAV, installateurs), *Nos produits → Clik&Yack / Clik&Yack Parts*, *Suivez vos commandes*.
2. **Catalogue** : `/produits` (produits finis) et `/pieces` (pièces détachées, 8 sous-familles) ; aucune carte n'affiche de prix. Fiche *Evolys 8 kW* : attributs, « Prix réservés aux clients connectés », pas de panier. Montrer une ancienne URL : `/fr_FR/taxons/nos-produits/clik-yack` → 301 `/produits`.
3. **Inscription PRO** (*Créer un compte*, `/inscription`) : profil PRO → société (SIRET faux `73282932000075` : « clé de contrôle incorrecte », puis `73282932000074` / `FR44732829320`) → contact → adresses (décocher « Livrer à cette adresse » pour une autre adresse de livraison) → « Votre compte est créé ».
4. **Activation** : MailHog → « Activez votre compte Clik&Yack » → lien `/activation/<token>` → « Votre compte est activé ». Se connecter (`/connexion`) : badge « Compte PRO », tableau de bord (société, SIRET, TVA, adresse). `/panier` explique que la commande est réservée aux comptes ECOM.
5. **Demande d'ouverture à la commande** (`/compte/acces-commande`) : formulaire pré-rempli (société, SIRET, contact, adresse), message libre → « demande envoyée ». Les fiches produits affichent « Demande d'ouverture à la commande en cours ».
6. **Back-office** (admin@yack.test, autre onglet) : tableau de bord → 1 demande en attente et la nouvelle inscription PRO. Fiche client → **Accepter** : le client passe ECOM, e-mail « Votre compte est ouvert à la commande » (MailHog). (Variante sans inscription : pro@yack.test / yack, ou pro2@.)
7. **Retour boutique** : badge « Compte ECOM », prix HT (Evolys 8 kW : 7 290,00 €). *Ajouter au panier*.
8. **SAV** (`/sav`) : `YK24HYD11S000321` → garantie simulée + 2 modèles → *module hydraulique intérieur* → vue éclatée : survol d'un repère = ligne surlignée (et inversement), prix et stock. *Ajouter* la pièce n°5 (résistance d'appoint 139 €) : même panier que la PAC.
9. **Commande** : `/panier` → *Commander* → *Adresse* (carnet : livraison ZA Les Milles, facturation siège) → *Livraison* (transporteur 19,90 € HT ou retrait agence) → *Paiement* : **Virement sous 15 jours** → *Récapitulatif* (escompte, TVA, CGV, commentaire) → **confirmation avec RIB**, référence `YACK-000000001`, date limite ; e-mail « Confirmation de votre commande n° 000000001 ».
10. **Back-office → commande** : *Paiements* → **Terminer** (virement reçu), n° de suivi → **Livrer** : e-mail « Votre commande … a été expédiée ».
11. **Client** : *Mon compte → Historique des commandes* : « Payée », « Expédiée » ; le détail affiche « Virement reçu », le n° de suivi, la ligne d'escompte et les totaux.
12. **Trouver un installateur** (`/trouver-un-installateur`) : 16 installateurs, filtres *Sud-Est + PAC air/eau + Dépannage* → **2 résultats** (Provence Clim Services, Vaucluse Thermique) sur la carte Leaflet.
13. **Synchro PIM/ERP** (terminal) : `make yack-sync` → 2 créés, 2 mis à jour (Evolys 8 kW à 6 990 €, compresseur Evolys en rupture), 1 désactivé, 2 rejets, code retour 1 ; log JSON `var/log/yack_sync.log`. Rafraîchir la boutique : nouveau prix, « Sur commande » dans la vue éclatée, produit désactivé en 404.
14. **Builder CMS** (http://localhost:8088/cms-admin/builder, admin@yack.test / yack ; aussi via *Contenu → Pages* du back-office) :
    - *Nouvelle page* « Offre printemps PAC » → adresse `offre-printemps-pac` proposée → *Créer et ouvrir le builder* ;
    - panneau *Blocs Yack* : **glisser** *Bandeau principal* et *Produits mis en avant* dans le canevas, **cliquer** *Bandeau d'appel à l'action* (ajout en fin de page) ; le canevas charge la feuille de style de la boutique ;
    - sélectionner un bloc → propriétés dans *Bloc sélectionné* (titre, accroche, visuel parmi une liste, fond, bouton, codes produits `YK-PAE-M06, YK-PAE-M08, YK-PAE-S11`…) ; ni gestionnaire de styles, ni bloc HTML, ni couleur libre ;
    - **Enregistrer en brouillon** (`/offre-printemps-pac` → 404 ; **Aperçu** avec bandeau « Aperçu du brouillon ») → **Publier** → *Voir la page* : produits mis en avant avec les prix si le visiteur est ECOM ;
    - mode *Mobile* : rendu responsive.

## Correspondance écrans ↔ cahier des charges

| Écran / démonstration | Section CDC couverte |
|---|---|
| *Nos produits → Clik&Yack / Clik&Yack Parts*, taxonomie multi-niveaux, attributs, marques | Catalogue produits finis et pièces détachées |
| Profils Visiteur / PRO / ECOM (prix et panier, règles portées par l'API) | Comptes clients, visibilité des prix |
| Inscription 4 étapes, activation par e-mail, mot de passe oublié, tableau de bord | Création de compte et espace client (§3.4) |
| Demande d'ouverture, BO *Demandes accès commande* et fiche client | Ouverture de compte à la commande (en attente / acceptée / refusée) |
| 3 moyens de paiement + « Escompte 2 % » (tunnel, confirmation, compte, BO) | Moyens de paiement, escompte |
| SAV : n° de série → modèles → vues éclatées → pièces (prix, stock, panier) ; garantie | Parcours SAV / pièces détachées, garantie |
| `app:yack:catalog:sync`, log `yack_sync` | Interface PIM/ERP |
| Trouver un installateur | Annuaire installateurs |
| Habillage Yack (magenta #A7357F, CTA noirs, en-tête logo + recherche + menu) | Charte |
| *Contenu → Pages*, builder GrapesJS à blocs verrouillés, pages publiques SSR, accueil piloté par la page *Accueil* | Gestion de contenu / landing pages |

*Les numéros de section du CDC ne figuraient pas dans le brief : correspondance à compléter par la production.*

## Organisation du code

### Sylius (`src/Yack/<Contexte>/{Domain,Application,Infrastructure}`)

- **Account** : `CustomerProfile` (Visitor / Pro / Ecom), `CommercePermission` (`YACK_SEE_PRICES`, `YACK_PLACE_ORDER`), `Siret` (SIRET + TVA). Inscription : ressource API + `RegisterCustomer` (client + utilisateur inactif + adresses, événement `sylius.customer.post_register` → e-mail d'activation). `CommerceAccessListener` ferme les ressources panier / commande de l'API aux non-ECOM ; `PriceHidingProductVariantNormalizer` met les prix à `null`. Demandes d'ouverture : entité `yack_order_access_request`, workflow, admin (`OrderAccessRequestAdminController`), e-mail `yack_order_access_granted`.
- **Catalog** : ports `ProductSource` (adapter `JsonFileProductSource`) et `ProductCatalog` (`SyliusProductCatalog`) ; message `SynchronizeCatalog` (Messenger) appelé par `app:yack:catalog:sync` et par la fixture `yack_catalog`.
- **AfterSales** : entités `src/Entity/AfterSales` (modèles, vues éclatées, repères, n° de série), ports `AfterSalesCatalog` (Doctrine) et `WarrantyProvider` (`FakeWarrantyProvider`), ressources API SAV. L'ajout d'une pièce au panier passe par l'API panier standard.
- **Installer** : port `InstallerDirectory`, adapter `InMemoryInstallerDirectory`, ressource API.
- **Payment** : escompte, modalités de virement (`PaymentInstructionExtension` pour les e-mails), `FakeCardPaymentListener`.
- **Content** : entité `CmsPage` (`blocks` JSON = source du rendu, `grapesData` = état de l'éditeur, statut brouillon / publié) ; `BlockType` (7 blocs) et `PageBlocks::normalize()` verrouillent la charte côté serveur (422 sinon) ; API `…/admin/cms-pages` (JWT admin) et `…/shop/cms-pages` (publiées seulement). `CmsExtension` ne sert plus qu'aux liens du back-office vers le front (`yack_cms_url()`, `yack_cms_builder_url()`).
- E-mails : gabarits `templates/shop/yack/email/`, déclarés via `sylius_mailer` dans `config/packages/yack.yaml` (`yack_front_url` pour les liens vers le front). La configuration de routage `sylius_shop` est conservée pour la sécurité (pare-feu shop), les liens « voir dans la boutique » de l'admin et Payum ; nginx n'envoie aucune de ces URL à PHP.

### Front (`front/`)

Routes, BFF, cookies et commandes : `front/README.md`. Blocs CMS : `front/src/lib/blocks/` (miroir de `BlockType`, classes `.yack-cms-*`) ; style unique `assets/shop/styles/yack.scss`, compilé par Encore (`/build/app/shop/app-shop-entry.css`) et chargé par la boutique, les pages publiques et le canevas du builder.

## Ce qui est réel / ce qui est simulé

| Réel (code réutilisable) | Simulé (adapters POC à remplacer) |
|---|---|
| Règle PRO / ECOM appliquée par l'API (prix `null`, 401 / 403 panier-commandes) | `JsonFileProductSource` lit `data/yack/*.json` au lieu de l'API PIM/ERP |
| Pipeline de synchro (création, mise à jour, désactivation, rejets, logs JSON, code retour) | `FakeWarrantyProvider` : dates déterministes par n° de série |
| Modèle SAV, parcours, panier unique PAC + pièces | Vues éclatées SVG dessinées pour la démo, images produits générées |
| Panier, tunnel, commandes, back-office : Sylius natif via l'API | `InMemoryInstallerDirectory` : 16 installateurs fictifs |
| Escompte par moyen de paiement, TVA sur le HT escompté | Carte bancaire : `FakeCardPaymentListener` (aucun PSP, aucun débit) |
| Inscription (SIRET / TVA, activation e-mail), demandes d'ouverture, e-mails | RIB fictif ; SIRET contrôlé par sa clé (pas d'API Sirene) ; pas de notification à Yack ; e-mails capturés par MailHog |
| BFF SvelteKit (SSR, JWT en cookies `httpOnly`), redirections 301 des anciennes URL | Données catalogue, marques, prix et stocks fictifs |
| Pages CMS : API, validation de la charte, builder (brouillon, aperçu, publication), rendu SSR | 6 visuels CMS fixes (`public/yack/cms`), pas de médiathèque |

Brancher les vraies API Yack = écrire un adapter HTTP pour `ProductSource`, `WarrantyProvider` et `InstallerDirectory` ; domaines, API et front restent inchangés.

## Limites connues

- **Recherche Meilisearch** (travail en cours hors POC : `src/Controller/Shop/`, `templates/shop/search/`, `src/Meilisearch/`) : elle vise l'ancienne boutique Twig supprimée. Sa route `/{_locale}/search` n'est plus atteignable (nginx l'envoie au front, qui ne la redirige pas). La recherche du front (`/recherche`) utilise l'API catalogue Sylius.
- Les montants du résumé du tunnel ne se recalculent qu'après « Suivant » quand on change de moyen de paiement.
- La livraison n'est pas soumise à la TVA (observé sur toutes les commandes de démo : TVA = 20 % du HT articles escompté, port exclu).
- Back-office : le « Total » du résumé de commande est l'`itemsTotal` natif (TTC, escompte déduit, hors port) ; le total Yack est la ligne « Total TTC ». Les dates du back-office sont en UTC, celles du front en heure de Paris.
- Le plugin Mollie reste installé : menu admin « Subscriptions » et légende du graphique du tableau de bord en anglais.
- PHPStan (niveau du dépôt) sur `src/Yack` : 6 erreurs de casts `mixed` dans le mapping JSON et la fixture du catalogue (`src/Yack/Catalog`).
- Pages CMS : une seule version par page (enregistrer en brouillon dépublie), pas d'historique ; session du builder limitée à la durée du JWT (1 h) ; `grapesData` est rechargé tel quel s'il existe ; dans le canevas, les liens sont inactifs et la largeur « Ordinateur » dépend de l'écran.
- Non portés depuis l'ancienne boutique Twig : avis produits, liste de souhaits, sélecteur de langue (FR seul).
- Les rubriques *Le groupe*, *Les marques* et *Nos documentations* sont des libellés sans page ; les anciennes URL de marques (`/fr_FR/taxons/marques/…`) répondent 404.
