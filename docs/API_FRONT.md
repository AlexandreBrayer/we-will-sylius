# API boutique Yack — contrat front (SvelteKit)

Référence de l'API consommée par la boutique headless `front/`. Base : `{SYLIUS}/api/v2` (local : `http://localhost:8088/api/v2`).
Toutes les réponses ci-dessous sont **réelles** (POC, jeux de démo `make yack-reset`), raccourcies (`…`) quand c'est indiqué.

- Formats : `Accept: application/ld+json` (Hydra : `hydra:member`, `hydra:totalItems`) ou `application/json` (tableaux nus).
  Corps : `Content-Type: application/ld+json` (ou `application/json`) ; **PATCH = `application/merge-patch+json`**.
- Montants : **entiers en centimes**, HT sauf mention contraire (`total`, `itemsTotal`… natifs Sylius sont TTC).
- Comptes de démo (mot de passe `yack`) : `pro@yack.test` (PRO), `ecom@yack.test` (ECOM). Canal `YACK_WEB`, locale `fr_FR`.
- Erreurs : `401 {"code":401,"message":"JWT Token not found"}` (non connecté sur une ressource protégée),
  `403/404/409` au format `hydra:Error` (`detail` = message affichable), `422` au format `ConstraintViolation`
  (`violations[{propertyPath, message}]`, messages en français).

## 1. Authentification

```bash
curl -s -X POST http://localhost:8088/api/v2/shop/customers/token \
  -H 'Content-Type: application/json' -d '{"email":"ecom@yack.test","password":"yack"}'
# 200 {"token":"eyJ0eXAiOiJKV1Qi…","customer":"/api/v2/shop/customers/1"}   — 401 si identifiants invalides ou compte non activé
```

Ensuite : `Authorization: Bearer <token>` (JWT sans état, durée de vie lexik par défaut). Déconnexion = oubli du jeton côté front.

## 2. Profil courant — `GET /shop/yack/me`

Public (jeton facultatif). C'est la source unique des droits côté front : `canSeePrices` (afficher les prix), `canOrder` (panier,
checkout, historique), `canRequestOrderAccess` (bouton « Commander en ligne » d'un PRO).

```bash
curl -s http://localhost:8088/api/v2/shop/yack/me                                   # visiteur
curl -s http://localhost:8088/api/v2/shop/yack/me -H "Authorization: Bearer $PRO"     # PRO
```

Visiteur :
```json
{
  "@context": "/api/v2/contexts/YackMe",
  "@id": "/api/v2/shop/yack/me",
  "@type": "YackMe",
  "authenticated": false,
  "customerId": null,
  "profile": "visitor",
  "profileLabel": "Visiteur",
  "canSeePrices": false,
  "canOrder": false,
  "canRequestOrderAccess": false,
  "email": null,
  "firstName": null,
  "lastName": null,
  "phoneNumber": null,
  "company": null,
  "siret": null,
  "vatNumber": null,
  "billingAddress": null,
  "orderAccessRequest": null
}
```

PRO (`pro@yack.test`) :
```json
{
  "@context": "/api/v2/contexts/YackMe",
  "@id": "/api/v2/shop/yack/me",
  "@type": "YackMe",
  "authenticated": true,
  "customerId": 2,
  "profile": "pro",
  "profileLabel": "Compte PRO",
  "canSeePrices": false,
  "canOrder": false,
  "canRequestOrderAccess": true,
  "email": "pro@yack.test",
  "firstName": "Julien",
  "lastName": "Installateur",
  "phoneNumber": "04 42 00 00 01",
  "company": "Provence Clim Services",
  "siret": "81234567800013",
  "vatNumber": "FR25812345678",
  "billingAddress": {
    "company": "Provence Clim Services",
    "street": "25 avenue de la Pinède",
    "postcode": "13090",
    "city": "Aix-en-Provence",
    "countryCode": "FR"
  },
  "orderAccessRequest": null
}
```

ECOM : même forme, `"profile": "ecom"`, `"canSeePrices": true`, `"canOrder": true`, `"canRequestOrderAccess": false`.

`orderAccessRequest` (dernière demande PRO → ECOM) : `{"status": "pending"|"accepted"|"refused", "statusLabel": "En attente", "createdAt": ISO 8601, "decidedAt": ISO 8601|null}`.

`customerId` : id du client pour les ressources natives qui l'exigent (`PUT /shop/customers/{id}`, `PUT /shop/customers/{id}/password`) ; le JWT ne porte que l'e-mail.

## 3. Règle prix / commande (côté serveur)

| | Visiteur | PRO | ECOM |
|---|---|---|---|
| Prix (produits, variantes, listes, pièces SAV) | `null` | `null` | oui |
| Panier / checkout / commandes | **401** | **403** | oui |

- Variantes et `defaultVariantData` des produits : `price`, `originalPrice`, `lowestPriceBeforeDiscount` valent `null` et `appliedPromotions`
  est retiré pour les visiteurs et les PRO.
- Toutes les ressources panier / commande sont fermées aux non-ECOM : `/shop/orders…`, `/shop/account/orders…`, `/shop/adjustments…`,
  `/shop/order-items…`, `/shop/order-item-units…`, `/shop/payments…`, `/shop/shipments…`, `/shop/payment-requests…`.
- Le tri `order[price]` reste disponible (il ne divulgue aucun montant).
- Produit non commercialisé en ligne (`yack.soldOnline` faux, attribut `vendu_en_ligne`) : prix de `defaultVariantData` à `null` pour
  tous les profils, ECOM compris ; `POST /shop/orders/{token}/items` répond **422** « Ce produit n'est pas commercialisé en ligne. ».

```bash
curl -s -X POST http://localhost:8088/api/v2/shop/orders -H 'Content-Type: application/ld+json' -d '{}'
# 401 {"code": 401, "message": "JWT Token not found"}
curl -s -X POST http://localhost:8088/api/v2/shop/orders -H "Authorization: Bearer $PRO" -H 'Content-Type: application/ld+json' -d '{}'
# 403 {"@type": "hydra:Error", "detail": "La commande en ligne est réservée aux comptes ECOM.", "status": 403}
```

Même 403 pour `POST /shop/orders/{token}/items` avec un jeton PRO.

## 4. Catalogue

Ressources natives Sylius (`/shop/products`, `/shop/products/{code}`, `/shop/products-by-slug/{slug}`, `/shop/products/{code}/attributes`,
`/shop/taxons/{code}`, `/shop/product-variants/{code}`), enrichies d'un bloc **`yack`** sur chaque produit (liste et fiche).

### Filtres

- `?yack_type=product` → produits finis (branche `clik_yack` du menu, 291 produits) ; `?yack_type=part` → pièces détachées
  (branche `clik_yack_parts`, 41) ; autre valeur → liste vide. Combinable avec les filtres natifs.
- Natifs : `productTaxons.taxon.code=<code>` (catégorie ou marque `marque_mhi`, `marque_yack`, `marque_rhoss` ; pièces : `marque_yack`, `marque_aerys`, `marque_hydrolia`),
  `translations.name=<texte>`, `order[code|createdAt|price]=asc|desc`, `localeCode=fr_FR&order[translation.name]=asc`, `page`, `itemsPerPage`.

```bash
curl -s 'http://localhost:8088/api/v2/shop/products?yack_type=part&itemsPerPage=1'
```
```json
{
  "hydra:totalItems": 41,
  "first": {
    "code": "YKP-ANO-THD",
    "name": "Anode titane chauffe-eau thermodynamique",
    "yack": {
      "type": "part",
      "typeLabel": "Pièce détachée",
      "brand": {
        "code": "marque_hydrolia",
        "name": "Hydrolia"
      },
      "range": "Aquaflow",
      "soldOnline": true,
      "attributes": [
        {
          "code": "type_article",
          "name": "Type d'article",
          "type": "text",
          "value": "Pièce détachée"
        },
        {
          "code": "marque",
          "name": "Marque",
          "type": "text",
          "value": "Hydrolia"
        },
        {
          "code": "gamme",
          "name": "Gamme",
          "type": "text",
          "value": "Aquaflow"
        },
        {
          "code": "reference_constructeur",
          "name": "Référence constructeur",
          "type": "text",
          "value": "AQF-ACI-TI"
        }
      ],
      "stock": {
        "tracked": true,
        "available": 12,
        "inStock": true,
        "label": "En stock"
      }
    }
  }
}
```

### Recherche : `GET /shop/yack/search` et `GET /shop/yack/search/suggest` (public)

Le moteur est choisi par `YACK_SEARCH_ENGINE` : `meilisearch` (pertinence, fautes de frappe, synonymes métier, référence cherchée
par son début) ou `database` (valeur par défaut, aucun service requis). Si Meilisearch ne répond pas en une seconde, la base répond
à sa place : `engine` dit qui a répondu. **Aucun prix** : la recherche renvoie des codes, les cartes produit (prix masqués ou non
selon le profil) se lisent ensuite sur `/shop/products/{code}`.

- `q` : texte saisi (espaces réduits, 100 caractères au plus) ; vide → aucun résultat. Un mot tapé comme une référence
  (`SRK25`, `FDC224`) est d'abord cherché dans les codes et références constructeur seulement (Meilisearch).
- `limit` : produits par page (24 par défaut, de 1 à 100) ; `page` : à partir de 1 ; `type` : `product` ou `part` (facultatif).
- Tous les mots sont exigés ; si aucun produit ne les a tous, les derniers deviennent facultatifs (Meilisearch).
- Synonymes : clim / climatisation / climatiseur / pompe à chaleur air/air ; pac / pompe à chaleur ; ecs / eau chaude sanitaire ; drv / vrf / vrv ;
  ue / unité extérieure / groupe extérieur ; ui / unité intérieure ; ballon / chauffe-eau ; thermostat / régulation…
  (`src/Yack/Catalog/Domain/SearchVocabulary.php`).

Réponses d'exemple : la forme est exacte, les valeurs dépendent du catalogue et du moteur.

```bash
curl -s 'http://localhost:8088/api/v2/shop/yack/search?q=FDC224&limit=9'
```
```json
{
  "query": "FDC224",
  "engine": "meilisearch",
  "total": 4,
  "page": 1,
  "limit": 9,
  "codes": ["FDC224KXZRE2", "FDC224KXZME1", "FDC224KXZWE1", "FDC224KXZPE1"]
}
```

Suggestions de l'en-tête : à partir de 2 caractères, 6 produits (vignette `sylius_shop_product_thumbnail`, `null` sans image)
et 3 catégories des branches Clik&Yack et Clik&Yack Parts (le front en déduit l'URL par l'arbre des catégories).

```bash
curl -s 'http://localhost:8088/api/v2/shop/yack/search/suggest?q=clim%20murale'
```
```json
{
  "query": "clim murale",
  "engine": "meilisearch",
  "products": [
    {
      "code": "SRK50ZT-WF",
      "name": "SRK50ZT-WF Mural Wifi intégré - 5kW - R32",
      "slug": "srk50zt-wf-mural-wifi-integre-5kw-r32-srk50zt-wf",
      "brand": "MHI",
      "image": "http://localhost:8088/media/cache/resolve/sylius_shop_product_thumbnail/…/srk50zt-wf.jpg"
    }
  ],
  "categories": [
    {"code": "pac_air_air_residentiel_ui_mural", "name": "Mural", "slug": "nos-produits/clik-yack/pompe-a-chaleur-air-air/residentiel/unite-interieure/mural"},
    {"code": "pac_air_air", "name": "Pompe à Chaleur Air/Air", "slug": "nos-produits/clik-yack/pompe-a-chaleur-air-air"}
  ]
}
```

Index : `make yack-search-up` (service et index) puis `make yack-search-reindex` après un changement hors synchronisation ;
chaque `make yack-sync` met à jour les produits qu'il applique. Front : `front/src/lib/server/search.ts` (page `/recherche`,
repli sur la recherche par nom de l'API catalogue si l'API de recherche échoue) et `front/src/lib/layout/SearchSuggest.svelte`.

### Produit — visiteur / PRO

```bash
curl -s http://localhost:8088/api/v2/shop/products/SRC80ZR-W
```
```json
{
  "code": "SRC80ZR-W",
  "name": "Groupe Extérieur Performance - 8kW - R32",
  "slug": "groupe-exterieur-performance-8kw-r32-src80zr-w",
  "defaultVariant": "/api/v2/shop/product-variants/SRC80ZR-W",
  "defaultVariantData": {
    "@context": "/api/v2/contexts/ProductVariant",
    "@id": "/api/v2/shop/product-variants/SRC80ZR-W",
    "@type": "ProductVariant",
    "name": null,
    "inStock": true,
    "price": null,
    "originalPrice": null,
    "lowestPriceBeforeDiscount": null
  },
  "yack": {
    "type": "product",
    "typeLabel": "Produit fini",
    "brand": {
      "code": "marque_mhi",
      "name": "MHI"
    },
    "range": "Pompe à Chaleur Air/Air",
    "soldOnline": true,
    "attributes": [
      {
        "code": "type_article",
        "name": "Type d'article",
        "type": "text",
        "value": "Produit fini"
      },
      {
        "code": "puissance_kw",
        "name": "Puissance (kW)",
        "type": "float",
        "value": 8
      },
      {
        "code": "fluide_frigorigene",
        "name": "Fluide frigorigène",
        "type": "text",
        "value": "R32"
      },
      {
        "code": "marque",
        "name": "Marque",
        "type": "text",
        "value": "MHI"
      },
      {
        "code": "gamme",
        "name": "Gamme",
        "type": "text",
        "value": "Pompe à Chaleur Air/Air"
      },
      {
        "code": "modele",
        "name": "Modèle",
        "type": "text",
        "value": "SRC80"
      },
      {
        "code": "typologie",
        "name": "Typologie",
        "type": "text",
        "value": "PAC Air/Air Monosplit"
      },
      {
        "code": "secteurs",
        "name": "Secteurs d'activité",
        "type": "text",
        "value": "Résidentiel"
      },
      {
        "code": "vendu_en_ligne",
        "name": "Commercialisé en ligne",
        "type": "checkbox",
        "value": true
      }
    ],
    "stock": {
      "tracked": true,
      "available": 20,
      "inStock": true,
      "label": "En stock"
    }
  }
}
```

Bloc `yack` :
- `type` : `"product"` | `"part"` | `null` ; `typeLabel` : « Produit fini » / « Pièce détachée ».
- `brand` : `{code, name}` — `code` = taxon marque (filtrable), `null` quand la marque ne vient que de l'attribut `marque`.
- `range` : gamme (attribut `gamme`). `attributes` : tous les attributs (`code`, `name` traduit, `type`, `value` brute).
- `soldOnline` : faux pour un produit non commercialisé en ligne (attribut `vendu_en_ligne` décoché) ; vrai sans l'attribut (pièces).
- `stock` (variante par défaut) : `tracked`, `available` (`onHand − onHold`, `null` si stock non suivi), `inStock`, `label` (« En stock » / « Sur commande »).

### Produit — ECOM

```bash
curl -s http://localhost:8088/api/v2/shop/products/SRC80ZR-W -H "Authorization: Bearer $ECOM"
```
```json
"defaultVariantData": {
  "@context": "/api/v2/contexts/ProductVariant",
  "@id": "/api/v2/shop/product-variants/SRC80ZR-W",
  "@type": "ProductVariant",
  "name": null,
  "inStock": true,
  "price": 179790,
  "originalPrice": 179790,
  "lowestPriceBeforeDiscount": null
}
```

Images : `images[].path` est une URL absolue (`http://localhost:8088/media/cache/resolve/sylius_original/…`) : photo de yack.fr téléchargée (`data/yack/images/reel/`) ou visuel de repli.

## 5. Inscription et activation

### `POST /shop/yack/registrations`

Règles de l'inscription en 4 étapes du front (`/inscription`) : profil `pro` | `ecom` ; raison sociale + SIRET obligatoires pour un PRO (SIRET
contrôlé : 14 chiffres + clé de Luhn, règle La Poste) ; n° de TVA facultatif mais cohérent avec le SIRET ; téléphone `^\+?[0-9 .()-]{10,20}$` ;
mot de passe ≥ 8 caractères ; `shippingAddress` absente = livraison à l'adresse de facturation. Le compte est créé **désactivé**
et l'e-mail d'activation contient `{FRONT_URL}/activation/{token}`.

```bash
curl -s -X POST http://localhost:8088/api/v2/shop/yack/registrations -H 'Content-Type: application/ld+json' -d '{
  "profile": "pro",
  "company": {"name": "Clim Test API", "siret": "73282932000074", "vatNumber": "FR44732829320"},
  "contact": {"firstName": "Jean", "lastName": "Api", "email": "jean.api@yack.test", "phone": "04 42 00 00 99", "password": "motdepasse"},
  "billingAddress": {"street": "1 rue du Test", "postcode": "13100", "city": "Aix-en-Provence", "countryCode": "FR"},
  "shippingAddress": null
}'
```
```json
201 {"@type": "RegistrationResult", "email": "jean.api@yack.test", "profile": "pro", "activationRequired": true}
```

Erreurs `422` (extrait réel, corps incomplet) :
```json
{"status": 422, "violations": [
  {"propertyPath": "company.name", "message": "Indiquez la raison sociale de votre entreprise."},
  {"propertyPath": "company.siret", "message": "Le SIRET doit comporter 14 chiffres valides (vérifiez la saisie, clé de contrôle incorrecte)."},
  {"propertyPath": "contact.email", "message": "Cette adresse e-mail n'est pas valide."},
  {"propertyPath": "contact.password", "message": "Choisissez un mot de passe."},
  {"propertyPath": "billingAddress.street", "message": "Indiquez l'adresse (numéro et voie)."}, …]}
```
E-mail déjà utilisé : `422`, `propertyPath: "contact.email"`, « Un compte existe déjà avec cette adresse e-mail. Connectez-vous ou utilisez « Mot de passe oublié ». »

### `POST /shop/yack/activations/{token}`

Sans corps. `204` : compte activé (+ e-mail de bienvenue avec lien `{FRONT_URL}/connexion`) ; `404` (`detail` « Lien d'activation invalide ou déjà utilisé. »).
Le jeton peut contenir `~ - _`.

```bash
curl -s -o /dev/null -w '%{http_code}' -X POST http://localhost:8088/api/v2/shop/yack/activations/pNWbMkW3xrJntGVMbWBPGk9lGr~8z93I…   # 204
# même appel une 2e fois → 404 ; POST /shop/customers/token avant activation → 401, après → 200
```

### Mot de passe oublié (natif Sylius)

```bash
curl -s -X POST http://localhost:8088/api/v2/shop/customers/reset-password -H 'Content-Type: application/json' -d '{"email":"jean.api@yack.test"}'
# 202 — e-mail avec {FRONT_URL}/mot-de-passe/reinitialiser/{token}
curl -s -X PATCH http://localhost:8088/api/v2/shop/customers/reset-password/$TOKEN -H 'Content-Type: application/merge-patch+json' \
  -d '{"newPassword":"nouveaumdp","confirmNewPassword":"nouveaumdp"}'
# 202 (corps vide)
```

### Mon compte (natif Sylius, JWT client)

| Appel | Usage front |
|---|---|
| `GET /shop/customers/{customerId}` | profil, `defaultAddress` (IRI) |
| `PUT /shop/customers/{id}` `{firstName, lastName, phoneNumber}` | `/compte/profil` (`422` : `firstName`…) ; l'e-mail n'est pas modifiable (sujet du JWT) |
| `PUT /shop/customers/{id}/password` `{currentPassword, newPassword, confirmNewPassword}` | `204` ; `422` sur `currentPassword` / `newPassword` |
| `GET` / `POST /shop/addresses`, `GET` / `PUT` / `DELETE /shop/addresses/{id}` | carnet d'adresses (`422` : `firstName`, `lastName`, `street`, `postcode`, `city`, `countryCode`) |
| `PUT /shop/customers/{id}` `{"defaultAddress": "/api/v2/shop/addresses/{id}"}` | adresse par défaut |
| `GET /shop/orders` | historique : paniers et commandes, sans filtre par n° ; le front garde `checkoutCompletedAt ≠ null`, trie et retrouve le `tokenValue` d'un n° avant `GET /shop/orders/{tokenValue}` |

## 6. Demande d'ouverture à la commande (PRO)

| Appel | Accès | Réponse |
|---|---|---|
| `GET /shop/yack/order-access-requests/draft` | PRO | formulaire prérempli depuis le compte |
| `POST /shop/yack/order-access-requests` | PRO | `201` demande créée ; `409` si une demande est déjà en attente ; `422` |
| `GET /shop/yack/order-access-requests` | connecté | demandes du client, plus récente d'abord |
| `GET /shop/yack/order-access-requests/{id}` | connecté (sa demande) | une demande, `404` sinon |

Visiteur → `401` ; ECOM sur `draft`/`POST` → `403`. La décision (acceptée / refusée) est prise dans le back-office ; acceptée, le client
passe ECOM et reçoit un e-mail avec un lien `{FRONT_URL}/produits`.

```bash
curl -s http://localhost:8088/api/v2/shop/yack/order-access-requests/draft -H "Authorization: Bearer $PRO"
# 200 {"company":"Clim Test API","siret":"73282932000074","fullName":"Jean Api","email":"jean.api@yack.test","phone":"04 42 00 00 99","address":"1 rue du Test\n13100 Aix-en-Provence","message":null}

curl -s -X POST http://localhost:8088/api/v2/shop/yack/order-access-requests -H "Authorization: Bearer $PRO" -H 'Content-Type: application/ld+json' \
  -d '{"company":"Clim Test API","siret":"73282932000074","fullName":"Jean Api","email":"jean.api@yack.test","phone":"04 42 00 00 99","address":"1 rue du Test\n13100 Aix-en-Provence","message":"Merci"}'
```
```json
201 {"@id": "/api/v2/shop/yack/order-access-requests/1", "@type": "YackOrderAccessRequest", "id": 1, "status": "pending", "statusLabel": "En attente",
     "company": "Clim Test API", "siret": "73282932000074", "fullName": "Jean Api", "email": "jean.api@yack.test", "phone": "04 42 00 00 99",
     "address": "1 rue du Test\n13100 Aix-en-Provence", "message": "Merci", "createdAt": "2026-11-06T09:59:13+00:00", "decidedAt": null}
409 {"@type": "hydra:Error", "status": 409, "detail": "Une demande d'ouverture à la commande est déjà en cours d'examen."}
```

## 7. SAV — n° de série → modèles → vues éclatées → pièces

Public. Numéros de démo : `YK25EVO08M001234` (SC-EVO-M08), `YK19KAZ35S004567` (SC-KAZ-I35 + SC-KAZ-O35),
`YK24HYD11S000321` (SC-HYD-I11 + SC-HYD-O11), `YK22THD200000789` (SC-THD-200). Un ServiceCode tapé directement fonctionne aussi.

### `GET /shop/yack/sav/serials/{n° de série ou ServiceCode}` (404 si inconnu)

```json
{
  "@context": "/api/v2/contexts/YackSavSerial",
  "@id": "/api/v2/shop/yack/sav/serials/YK19KAZ35S004567",
  "@type": "YackSavSerial",
  "query": "YK19KAZ35S004567",
  "serial": "YK19KAZ35S004567",
  "isServiceCode": false,
  "warranty": {
    "serialNumber": "YK19KAZ35S004567",
    "commissionedAt": "2019-06-03",
    "partsCoverageEnd": "2021-06-03",
    "compressorCoverageEnd": "2024-06-03",
    "partsCovered": false,
    "compressorCovered": false,
    "source": "Simulation API garantie Yack (POC)"
  },
  "models": [
    {
      "serviceCode": "SC-KAZ-I35",
      "name": "Mural Titane Wifi intégré 2,5 kW : unité intérieure",
      "family": "PAC air/air monosplit",
      "explodedViewCount": 1,
      "partCount": 6,
      "finishedProduct": {
        "code": "SRK25ZS-WFT",
        "slug": "mural-titane-wifi-integre-2-5kw-r32-srk25zs-wft",
        "name": "Mural Titane Wifi intégré - 2,5kW - R32"
      }
    },
    {
      "serviceCode": "SC-KAZ-O35",
      "name": "Groupe extérieur Performance 2,5 kW : unité extérieure",
      "family": "PAC air/air monosplit",
      "explodedViewCount": 1,
      "partCount": 6,
      "finishedProduct": {
        "code": "SRC25ZS-W2",
        "slug": "groupe-exterieur-performance-2-5kw-r32-src25zs-w2",
        "name": "Groupe Extérieur Performance - 2,5kW - R32"
      }
    }
  ]
}
```
`warranty` vaut `null` pour un ServiceCode ; `partsCovered` / `compressorCovered` = couverture à la date du jour.

### `GET /shop/yack/sav/models/{serviceCode}`

```json
{
  "@context": "/api/v2/contexts/YackSavModel",
  "@id": "/api/v2/shop/yack/sav/models/SC-EVO-M08",
  "@type": "YackSavModel",
  "serviceCode": "SC-EVO-M08",
  "name": "PAC air/eau RHOSS R32 : groupe monobloc",
  "family": "PAC air/eau monobloc",
  "partCount": 13,
  "finishedProduct": {
    "code": "RHOSS-AIREAU-R32",
    "slug": "groupe-d-eau-glacee-et-pompe-a-chaleur-air-eau-rhoss-aireau-r32",
    "name": "Groupe d'eau glacée et pompe à chaleur Air/Eau"
  },
  "explodedViews": [
    {
      "id": 1,
      "name": "Circuit frigorifique & ventilation",
      "imagePath": "/yack/sav/evolys-frigo.svg",
      "imageUrl": "http://localhost:8088/yack/sav/evolys-frigo.svg",
      "partCount": 6
    },
    {
      "id": 2,
      "name": "Hydraulique & électronique",
      "imagePath": "/yack/sav/evolys-hydro.svg",
      "imageUrl": "http://localhost:8088/yack/sav/evolys-hydro.svg",
      "partCount": 7
    }
  ]
}
```

### `GET /shop/yack/sav/exploded-views/{id}` (ECOM, 2 premières pièces)

```json
{
  "@context": "/api/v2/contexts/YackSavExplodedView",
  "@id": "/api/v2/shop/yack/sav/exploded-views/1",
  "@type": "YackSavExplodedView",
  "id": 1,
  "name": "Circuit frigorifique & ventilation",
  "imagePath": "/yack/sav/evolys-frigo.svg",
  "imageUrl": "http://localhost:8088/yack/sav/evolys-frigo.svg",
  "model": {
    "serviceCode": "SC-EVO-M08",
    "name": "Evolys monobloc 8 kW — groupe extérieur",
    "family": "PAC air/eau monobloc"
  },
  "views": [
    {
      "id": 1,
      "name": "Circuit frigorifique & ventilation"
    },
    {
      "id": 2,
      "name": "Hydraulique & électronique"
    }
  ],
  "items": [
    {
      "marker": 1,
      "x": 21.9,
      "y": 40,
      "quantity": 1,
      "part": {
        "code": "YKP-FAN-EVO-H",
        "name": "Hélice ventilateur Ø 550 mm Evolys / Hydra",
        "slug": "helice-ventilateur-o-550-mm-evolys-hydra-ykp-fan-evo-h",
        "enabled": true,
        "variant": "/api/v2/shop/product-variants/YKP-FAN-EVO-H",
        "variantCode": "YKP-FAN-EVO-H",
        "price": 8900,
        "stock": {
          "tracked": true,
          "available": 7,
          "inStock": true,
          "label": "En stock"
        }
      }
    },
    {
      "marker": 2,
      "x": 28.75,
      "y": 50,
      "quantity": 1,
      "part": {
        "code": "YKP-MOT-EVO-F",
        "name": "Moteur ventilateur DC Evolys",
        "slug": "moteur-ventilateur-dc-evolys-ykp-mot-evo-f",
        "enabled": true,
        "variant": "/api/v2/shop/product-variants/YKP-MOT-EVO-F",
        "variantCode": "YKP-MOT-EVO-F",
        "price": 23900,
        "stock": {
          "tracked": true,
          "available": 5,
          "inStock": true,
          "label": "En stock"
        }
      }
    }
  ]
}
```

- `x`, `y` : position du repère en % de l'image (`imageUrl` = SVG servi par Sylius, `imagePath` relatif à la même origine).
- `part.price` : centimes HT, **`null` pour visiteur et PRO** (et pour une pièce plus commercialisée) ; `stock.label` vaut « Plus commercialisée » si `enabled: false`.
- Ajout au panier (ECOM) : `POST /shop/orders/{token}/items {"productVariant": part.variant, "quantity": n}` (`variant` `null` = non commandable).

## 8. Trouver un installateur

```bash
curl -s 'http://localhost:8088/api/v2/shop/yack/installers?sector=Sud-Est&activity=depannage'
```
```json
{
  "@context": "/api/v2/contexts/YackInstallers",
  "@id": "/api/v2/shop/yack/installers",
  "@type": "YackInstallers",
  "criteria": {
    "sector": "Sud-Est",
    "range": null,
    "activity": "depannage"
  },
  "count": 3,
  "installers": [
    {
      "name": "Provence Clim Services",
      "city": "Aix-en-Provence",
      "postcode": "13100",
      "sector": "Sud-Est",
      "ranges": [
        "PAC air/eau",
        "PAC air/air",
        "Climatisation"
      ],
      "activities": [
        {
          "code": "installation",
          "label": "Installation"
        },
        {
          "code": "maintenance",
          "label": "Entretien"
        },
        {
          "code": "depannage",
          "label": "Dépannage"
        }
      ],
      "latitude": 43.5297,
      "longitude": 5.4474,
      "phone": "04 00 00 01 01",
      "certifiedRge": true
    }
  ]
}
```
Paramètres facultatifs `sector`, `range`, `activity` (valeurs de `/filters` ; une valeur inconnue est ignorée — voir `criteria`).

```bash
curl -s http://localhost:8088/api/v2/shop/yack/installers/filters
```
```json
{"sectors": ["Auvergne-Rhône-Alpes", "Grand Ouest", "Nouvelle-Aquitaine", "Occitanie", "Sud-Est", "Île-de-France"], "ranges": ["PAC air/eau", "PAC air/air", "Climatisation", "Eau chaude sanitaire"], "activities": [{"code": "installation", "label": "Installation"}, {"code": "maintenance", "label": "Entretien"}, {"code": "depannage", "label": "Dépannage"}]}
```

## 9. Panier et checkout (ECOM)

Flux natif Sylius ; chaque réponse `Order` contient en plus le bloc **`yack`** (totaux, escompte, paiement, suivi).

```bash
H=(-H "Authorization: Bearer $ECOM" -H 'Content-Type: application/ld+json')
# 1. panier                       → 201, tokenValue ; l'adresse de facturation du compte est préremplie
curl -s -X POST http://localhost:8088/api/v2/shop/orders "${H[@]}" -d '{}'
# 2. article                      → 201
curl -s -X POST http://localhost:8088/api/v2/shop/orders/$T/items "${H[@]}" \
  -d '{"productVariant":"/api/v2/shop/product-variants/SRC80ZR-W","quantity":1}'
#    quantité : PATCH /shop/orders/$T/items/{itemId} {"quantity":2} ; suppression : DELETE /shop/orders/$T/items/{itemId}
# 3. adresses                     → 200, checkoutState "addressed"
curl -s -X PUT http://localhost:8088/api/v2/shop/orders/$T "${H[@]}" -d '{"email":"ecom@yack.test",
  "billingAddress":{"firstName":"Émilie","lastName":"Ecom","company":"Clim Express","street":"10 rue du Soleil","city":"Aix-en-Provence","postcode":"13100","countryCode":"FR","phoneNumber":"0442000010"},
  "shippingAddress":{…même forme…}}'
# 4. livraison (choix : GET /shop/orders/$T/shipments/{id}/methods ; yack_transport 19,90 € HT, yack_agence 0 €)
curl -s -X PATCH http://localhost:8088/api/v2/shop/orders/$T/shipments/$SHIPMENT_ID -H "Authorization: Bearer $ECOM" \
  -H 'Content-Type: application/merge-patch+json' -d '{"shippingMethod":"yack_transport"}'
# 5. paiement (choix : GET /shop/orders/$T/payments/{id}/methods ou /shop/payment-methods) — recalcule escompte + TVA
curl -s -X PATCH http://localhost:8088/api/v2/shop/orders/$T/payments/$PAYMENT_ID -H "Authorization: Bearer $ECOM" \
  -H 'Content-Type: application/merge-patch+json' -d '{"paymentMethod":"virement_15j"}'
# 6. validation                   → 200, number, checkoutState "completed" ; e-mail de confirmation (lien {FRONT_URL}/compte/commandes/{number})
curl -s -X PATCH http://localhost:8088/api/v2/shop/orders/$T/complete -H "Authorization: Bearer $ECOM" \
  -H 'Content-Type: application/merge-patch+json' -d '{"notes":null}'
```

Évolution de `yack.totals` (SRC80ZR-W, transport) :

| Étape | escompte | TVA | total TTC |
|---|---|---|---|
| après l'article / l'adresse | 0 | 36356 | 218136 |
| livraison choisie (moyen présélectionné `virement_15j`) | −3596 | 35637 | **213821** |
| `virement_echeance` (sans escompte) | 0 | 36356 | 218136 |
| `virement_15j` | −3596 | 35637 | **213821** |

Soit 1 797,90 − 35,96 (escompte 2 %) = 1 761,94 € HT + TVA 352,39 ; transport 19,90 € HT + **TVA 3,98** (taux produit) ; **total 2 138,21 € TTC**.

### Moyens de paiement — `escompteRate` / `escompteLabel`

```json
{
  "id": 1,
  "code": "virement_15j",
  "position": 0,
  "name": "Virement sous 15 jours",
  "description": "Escompte de 2 % pour un règlement par virement sous 15 jours.",
  "instructions": "Virement à effectuer sous 15 jours sur le compte Yack indiqué sur la facture (démo).",
  "escompteRate": 2,
  "escompteLabel": "Escompte 2 %"
}
```
`carte_bancaire` : 2 / « Escompte 2 % » ; `virement_echeance` : 0 / `null`.

### Commande validée — `GET /shop/orders/{token}` (extrait)

```json
{
  "@id": "/api/v2/shop/orders/XSSrreihQZD_bbVVCaQKKHWj03Rz8GNpqMfsW-kUOuD7--h77r3jECTPAa98DLho",
  "tokenValue": "XSSrreihQZD_bbVVCaQKKHWj03Rz8GNpqMfsW-kUOuD7--h77r3jECTPAa98DLho",
  "number": "000000001",
  "checkoutState": "completed",
  "paymentState": "awaiting_payment",
  "shippingState": "ready",
  "total": 213821,
  "yack": {
    "totals": {
      "itemsExclTax": 179790,
      "promotion": 0,
      "escompte": -3596,
      "itemsNetExclTax": 176194,
      "shippingExclTax": 1990,
      "itemsTax": 35239,
      "shippingTax": 398,
      "taxTotal": 35637,
      "totalExclTax": 178184,
      "total": 213821
    },
    "escompte": {
      "label": "Escompte 2 %",
      "amount": -3596
    },
    "paymentMethod": {
      "code": "virement_15j",
      "name": "Virement sous 15 jours",
      "escompteRate": 2,
      "escompteLabel": "Escompte 2 %"
    },
    "paymentInstruction": {
      "kind": "virement",
      "amount": 213821,
      "paid": false,
      "bankAccount": {
        "holder": "YACK SAS (compte de démonstration)",
        "bank": "Banque Fictive du Sud — Aix-en-Provence",
        "iban": "FR76 3000 1007 9412 3456 7890 185",
        "bic": "BFSUFRPPXXX"
      },
      "reference": "YACK-000000001",
      "dueDate": "2026-11-21"
    },
    "paymentStatus": {
      "state": "awaiting_payment",
      "label": "Virement attendu"
    },
    "shippingStatus": {
      "state": "ready",
      "label": "En préparation"
    },
    "shipments": [
      {
        "id": 2,
        "method": {
          "code": "yack_transport",
          "name": "Livraison transporteur (24/48 h)"
        },
        "state": "ready",
        "tracking": null,
        "shippedAt": null
      }
    ],
    "items": [
      {
        "id": 11,
        "variantCode": "SRC80ZR-W",
        "productCode": "SRC80ZR-W",
        "productSlug": "groupe-exterieur-performance-8kw-r32-src80zr-w",
        "image": "http://localhost:8088/media/cache/sylius_shop_product_thumbnail/0b/28/02be15b10ac960ae35bd4ba645db.png",
        "type": "product",
        "typeLabel": "Produit fini"
      }
    ]
  }
}
```

Bloc `yack` :
- `totals` (centimes) : `itemsExclTax` (HT brut), `promotion`, `escompte` (négatif), `itemsNetExclTax`, `shippingExclTax`, `itemsTax`,
  `shippingTax`, `taxTotal`, `totalExclTax`, `total` (TTC). À utiliser à la place de `shippingTotal` / `shippingTaxTotal` natifs (libellés trompeurs).
- `escompte` : `{label, amount}` ou `null`. `paymentMethod` : `{code, name, escompteRate, escompteLabel}`.
- `paymentInstruction` (une fois la commande validée) : virement → `kind: "virement"`, RIB, référence `YACK-<n°>`, `dueDate` (`Y-m-d`), `paid`
  (virement reçu) ; carte → `{"kind": "carte", "amount": 20933, "paid": true, "bankAccount": null, "reference": null, "dueDate": null}`
  (paiement simulé, commande `paid`).
- `paymentStatus` / `shippingStatus` : `{state, label}` (« Virement attendu », « Payée », « En préparation », « Expédiée »…).
- `shipments[]` : `{id, method{code,name}, state, tracking, shippedAt}` (n° de suivi saisi au back-office à l'expédition).
- `items[]` (panier et commande) : `{id, variantCode, productCode, productSlug, image, type, typeLabel}` par ligne, même `id` que
  `items[].id` natif — de quoi afficher vignette (URL absolue, filtre `sylius_shop_product_thumbnail`, `null` sans image), code, lien
  fiche et badge produit fini / pièce détachée sans appel catalogue supplémentaire.

### Historique — `GET /shop/orders` (ECOM)

Collection native (`number`, `tokenValue`, `checkoutCompletedAt`, `state`, `itemsTotal`…) + bloc `yack` ; le total TTC est `yack.totals.total`.
Le détail se charge par `tokenValue` (retrouvé dans la liste à partir du n° de commande des e-mails).

## 10. E-mails → liens front

Variable d'environnement **`FRONT_URL`** (défaut `http://localhost:8088`, paramètre `app.yack.front_url`, global Twig `yack_front_url`) :

| E-mail | Lien |
|---|---|
| Activation (inscription, renvoi API) | `{FRONT_URL}/activation/{token}` |
| Bienvenue (compte activé) | `{FRONT_URL}/connexion` |
| Mot de passe oublié | `{FRONT_URL}/mot-de-passe/reinitialiser/{token}` |
| Accès commande accordé | `{FRONT_URL}/produits` |
| Confirmation de commande / expédition | `{FRONT_URL}/compte/commandes/{number}` |
| En-tête (logo, accueil) | `{FRONT_URL}/yack/logo-yack.png`, `{FRONT_URL}/` |

`/yack/*` (logo, SVG des vues éclatées, visuels CMS) doit donc rester servi sur `FRONT_URL` (proxy vers Sylius).

## 11. Templates CMS (fiche produit, page catégorie)

Mise en page des pages communes, construite dans le builder comme une page CMS (`blocks` = source du rendu, `grapesData` = état de l'éditeur, `status` `draft|published`) avec un `type` :
`product` (fiche `/produit/{slug}`) ou `taxon` (pages catégorie `/produits/…`, `/pieces/…`). Un seul **défaut publié** par type.

**Résolution** (`App\Yack\Content\Application\CmsTemplateResolver`, templates **publiés** uniquement — un template affecté mais en brouillon est ignoré, comme s'il n'était pas affecté) :
- produit → template du produit, sinon *template produits* de la catégorie principale puis de ses ancêtres, sinon défaut `product` ;
- catégorie → template de la catégorie, sinon de ses ancêtres, sinon défaut `taxon`.

**Affectations** (même donnée que le BO : champ « Template CMS » des formulaires produit et taxon) :
`sylius_product.cms_template_id` (template produit), `sylius_taxon.product_cms_template_id` (template produit des produits de la catégorie et de ses sous-catégories), `sylius_taxon.cms_template_id` (template catégorie de la page et de ses sous-catégories).

### Blocs

Blocs libres des pages (§ `BlockType` : `hero`, `texte-image`, `produits`, `categories`, `cta`, `faq`, `reassurance`) autorisés partout, plus des **blocs liés** au contexte de la page (aucune prop de donnée : ils lisent le produit / la catégorie rendu), autorisés seulement dans un template du bon type.
Props : liste de valeurs (1re = défaut) ou texte libre (défaut `''` ; pour `title`, `''` = intitulé par défaut du bloc). Un bloc sans prop est renvoyé avec `"props": []` (tableau PHP vide) : le traiter comme `{}`.

| `type` | Contrat | Libellé | Props |
|---|---|---|---|
| `produit-galerie` | ProductGallery | Galerie produit | — |
| `produit-infos` | ProductInfo | Informations produit (nom, marque, gamme, code, description courte) | — |
| `produit-achat` | ProductBuyBox | Achat (prix, stock, panier) — **obligatoire, unique** | — |
| `produit-caracteristiques` | ProductAttributes | Caractéristiques techniques | `title` |
| `produit-description` | ProductDescription | Description | `title` |
| `produit-documents` | ProductDocuments | Documents | `title` |
| `produit-sav` | ProductSavLink | Lien SAV | `title`, `text` |
| `produits-associes` | RelatedProducts | Produits associés | `title`, `limit` `4\|8` |
| `categorie-entete` | TaxonHeader | En-tête de catégorie | `description` `oui\|non` |
| `categorie-produits` | TaxonProductGrid | Liste des produits (filtres, tri, pagination) — **obligatoire, unique** | `perPage` `9\|18\|27`, `sort` `position\|nom-asc\|nom-desc\|recent\|ancien` (tri initial) |
| `categorie-sous-categories` | TaxonChildren | Sous-catégories | `title` |

Validation à l'enregistrement (422, `propertyPath: "blocks"`) : bloc lié hors de son type, bloc obligatoire absent ou en double. Une page CMS (`/admin/cms-pages`) refuse tout bloc lié.

### Shop — `GET /shop/cms-templates/resolve?type=product|taxon&code={code}` (public)

```bash
curl -s 'http://localhost:8088/api/v2/shop/cms-templates/resolve?type=product&code=<code>' -H 'Accept: application/json'
# 200 : produit dont la catégorie principale descend de pac_air_eau
{"id":2,"type":"product","name":"PAC premium","source":"taxon","sourceCode":"pac_air_eau",
 "blocks":[{"type":"hero","props":{"image":"yack/cms/pac-monobloc.png","theme":"sombre","title":"Pompes à chaleur air/eau Clik&Yack","ctaUrl":"/trouver-un-installateur","ctaLabel":"Trouver un installateur","subtitle":"Jusqu'à 5 ans de garantie sur les compresseurs, …"}},
           {"type":"produit-galerie","props":[]},{"type":"produit-infos","props":[]},{"type":"produit-achat","props":[]},
           {"type":"reassurance","props":{…}},{"type":"produit-description","props":{"title":"Détails"}},
           {"type":"produit-caracteristiques","props":{"title":"Caractéristiques techniques"}},{"type":"produit-sav","props":{…}},{"type":"faq","props":{…}}],
 "updatedAt":"2026-11-06 16:37:32"}

curl -s '…/shop/cms-templates/resolve?type=product&code=SRK25ZS-WFT'   # mural PAC air/air
# 200 {"id":1,"type":"product","name":"Standard","source":"default","sourceCode":null,"blocks":[{"type":"produit-galerie","props":[]},…],"updatedAt":"…"}

curl -s '…/shop/cms-templates/resolve?type=taxon&code=parts_compresseurs'   # sous-catégorie de clik_yack_parts
# 200
{"id":4,"type":"taxon","name":"Pièces détachées","source":"taxon","sourceCode":"clik_yack_parts",
 "blocks":[{"type":"categorie-entete","props":{"description":"oui"}},
           {"type":"cta","props":{"text":"Saisissez son numéro de série : …","theme":"magenta","title":"Vous cherchez la pièce d'un appareil précis ?","ctaUrl":"/sav","ctaLabel":"Rechercher par n° de série"}},
           {"type":"categorie-sous-categories","props":{"title":"Familles de pièces"}},
           {"type":"categorie-produits","props":{"sort":"nom-asc","perPage":"18"}}],
 "updatedAt":"2026-11-06 16:37:32"}

# source : product (affecté au produit) | taxon (catégorie elle-même ou ancêtre = sourceCode) | default
# 400 {"detail":"Paramètre « type » attendu : product ou taxon.", …}   404 {"detail":"Produit « NOPE » introuvable.", …}
# 404 « Aucun template publié ne s'applique (pas de template par défaut). »
```

### Admin (JWT administrateur : `POST /admin/administrators/token` `{"email":"admin@yack.test","password":"yack"}`)

| Méthode | Route | Corps / effet |
|---|---|---|
| GET | `/admin/cms-templates` | liste (type puis nom) : `id, type, name, isDefault, status, createdAt, updatedAt, assignments{products, taxons}` |
| GET | `/admin/cms-templates/{id}` | + `blocks`, `grapesData` |
| POST | `/admin/cms-templates` | `{"type":"product"\|"taxon" (défaut product, figé ensuite),"name","status","isDefault","blocks","grapesData"}` → 201 |
| PUT | `/admin/cms-templates/{id}` | champs modifiables : `name, status, isDefault, blocks, grapesData` (corps partiel accepté) |
| DELETE | `/admin/cms-templates/{id}` | 204 ; affectations remises à « hérité » ; **409** pour le défaut |
| POST | `/admin/cms-templates/{id}/duplicate` | sans corps → 201, brouillon « Copie de … », non défaut, sans affectation |
| GET / PUT | `/admin/cms-templates/{id}/assignments` | `{"productCodes":[…],"taxonCodes":[…]}` remplace les deux listes |

Défaut : enregistrer un template avec `isDefault: true` retire le drapeau à l'ancien défaut du type ; le défaut doit être publié (422 `isDefault`) et ne peut ni perdre son drapeau directement (422 : en définir un autre) ni être supprimé (409).

```bash
T=$(curl -s -X POST http://localhost:8088/api/v2/admin/administrators/token -H 'Content-Type: application/json' \
  -d '{"email":"admin@yack.test","password":"yack"}' | jq -r .token)

curl -s http://localhost:8088/api/v2/admin/cms-templates -H "Authorization: Bearer $T" -H 'Accept: application/json'
# 200 [{"id":2,"type":"product","name":"PAC premium","isDefault":false,"status":"published","createdAt":"2026-11-06 16:37:32","updatedAt":"2026-11-06 16:37:32","assignments":{"products":0,"taxons":1}},
#      {"id":1,"type":"product","name":"Standard","isDefault":true,"status":"published",…,"assignments":{"products":0,"taxons":0}},
#      {"id":4,"type":"taxon","name":"Pièces détachées","isDefault":false,…,"assignments":{"products":0,"taxons":1}},
#      {"id":3,"type":"taxon","name":"Standard","isDefault":true,…}]

curl -s -X POST http://localhost:8088/api/v2/admin/cms-templates -H "Authorization: Bearer $T" -H 'Content-Type: application/json' \
  -d '{"type":"product","name":"Test","blocks":[{"type":"produit-galerie"},{"type":"hero","props":{"title":"x"}}]}'
# 422 {"violations":[{"propertyPath":"blocks","message":"Un template produit doit contenir le bloc « Achat (prix, stock, panier) »."}], …}

curl -s -X POST … -d '{"type":"product","name":"Test","blocks":[{"type":"produit-achat"},{"type":"categorie-produits"}]}'
# 422 {"violations":[{"propertyPath":"blocks","message":"Bloc n°2 (Liste des produits) : réservé aux templates catégorie."}], …}

curl -s -X POST … -d '{"type":"product","name":"Test","blocks":[{"type":"produit-achat"},{"type":"produit-achat"}]}'
# 422 « Le bloc « Achat (prix, stock, panier) » ne peut figurer qu'une seule fois dans le template. »

curl -s -X POST … -d '{"blocks":[{"type":"categorie-produits"}],"type":"taxon","name":"Test taxon"}'
# 201 {"id":5,"type":"taxon","name":"Test taxon","isDefault":false,"status":"draft","blocks":[{"type":"categorie-produits","props":{"sort":"position","perPage":"9"}}],"grapesData":null,…,"assignments":{"products":0,"taxons":0}}

curl -s -X PUT http://localhost:8088/api/v2/admin/cms-templates/5 … -d '{"isDefault":true}'
# 422 {"violations":[{"propertyPath":"isDefault","message":"Le template par défaut doit être publié."}], …}
curl -s -X DELETE http://localhost:8088/api/v2/admin/cms-templates/3 …
# 409 {"detail":"Le template par défaut ne peut pas être supprimé : définissez d'abord un autre template par défaut.", …}

curl -s -X POST http://localhost:8088/api/v2/admin/cms-templates/2/duplicate -H "Authorization: Bearer $T" -H 'Accept: application/json'
# 201 {"id":6,"type":"product","name":"Copie de PAC premium","isDefault":false,"status":"draft","blocks":[…],"grapesData":null,…}

curl -s http://localhost:8088/api/v2/admin/cms-templates/2/assignments -H "Authorization: Bearer $T" -H 'Accept: application/json'
# 200 {"id":2,"type":"product","productCodes":[],"taxonCodes":["pac_air_eau"],"products":[],"taxons":[{"code":"pac_air_eau","name":"Pompe à Chaleur Air/Eau"}]}

curl -s -X PUT http://localhost:8088/api/v2/admin/cms-templates/6/assignments -H "Authorization: Bearer $T" -H 'Content-Type: application/json' \
  -d '{"productCodes":["SRK25ZS-WFT"],"taxonCodes":["ecs"]}'
# 200 {"id":6,"type":"product","productCodes":["SRK25ZS-WFT"],"taxonCodes":["ecs"],
#      "products":[{"code":"SRK25ZS-WFT","name":"Mural Titane Wifi intégré - 2,5kW - R32"}],"taxons":[{"code":"ecs","name":"Eau Chaude Sanitaire"}]}
# 422 {"violations":[{"propertyPath":"productCodes","message":"Produit(s) inconnu(s) : NOPE."}]} (idem taxonCodes : « Catégorie(s) inconnue(s) : … »)
# 422 template catégorie + productCodes : « Un template catégorie ne s'affecte qu'à des catégories. »
```

Un code listé passe sur ce template (même s'il en avait un autre) ; un code retiré redevient « hérité ». Recherche de produits / catégories à ajouter : endpoints natifs `GET /admin/products`, `GET /admin/taxons`.
