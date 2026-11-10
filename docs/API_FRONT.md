# API boutique Yack : contrat front (SvelteKit)

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
# 200 {"token":"eyJ0eXAiOiJKV1Qi…","customer":"/api/v2/shop/customers/1"}   (401 si identifiants invalides ou compte non activé)
```

Ensuite : `Authorization: Bearer <token>` (JWT sans état, durée de vie lexik par défaut). Déconnexion = oubli du jeton côté front.

## 2. Profil courant : `GET /shop/yack/me`

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
- Disponibilité (`yack.stock.level`) : `en_stock` (commandable) ou `rupture` (« Rupture de stock » : le front n'affiche ni quantité ni
  « Ajouter au panier »). Elle ne change pas la règle des prix : un produit en rupture garde son prix pour l'ECOM. Côté serveur, Sylius
  refuse l'ajout : `POST /shop/orders/{token}/items` répond **422** « La variante de produit avec le code … n'a pas de stock suffisant. ».

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
        "label": "En stock",
        "level": "en_stock"
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
- `limit` : produits par page (24 par défaut, de 1 à 100) ; `page` : à partir de 1 ; `type` : `product` ou `part` (facultatif) ; `category` : code d'une catégorie, la recherche se limite aux produits dont la catégorie principale est celle-ci ou une de ses sous-catégories (facultatif, « Rechercher dans la liste » des pages catégorie).
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

### Produit : visiteur / PRO

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
      }
    ],
    "stock": {
      "tracked": true,
      "available": 20,
      "inStock": true,
      "label": "En stock",
      "level": "en_stock"
    }
  }
}
```

Bloc `yack` :
- `type` : `"product"` | `"part"` | `null` ; `typeLabel` : « Produit fini » / « Pièce détachée ».
- `brand` : `{code, name}` : `code` = taxon marque (filtrable), `null` quand la marque ne vient que de l'attribut `marque`.
- `range` : gamme (attribut `gamme`). `attributes` : tous les attributs (`code`, `name` traduit, `type`, `value` brute).
- `stock` (variante par défaut) : `tracked`, `available` (`onHand − onHold`, `null` si stock non suivi), `inStock`, `label` et `level` :
  `en_stock` « En stock » (commandable) ou `rupture` « Rupture de stock » (stock épuisé ou aucune variante en vente : ni panier ni
  commande). Aucun autre niveau sur les produits : pas de seuil « stock faible », pas de « sur commande ».

### Produit : ECOM

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
# 202 : e-mail avec {FRONT_URL}/mot-de-passe/reinitialiser/{token}
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

## 7. SAV : n° de série → modèles → vues éclatées → pièces

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
    "name": "Evolys monobloc 8 kW : groupe extérieur",
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
          "available": 10,
          "inStock": true,
          "label": "En stock",
          "level": "en_stock"
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
          "label": "En stock",
          "level": "en_stock"
        }
      }
    }
  ]
}
```

- `x`, `y` : position du repère en % de l'image (`imageUrl` = SVG servi par Sylius, `imagePath` relatif à la même origine).
- `part.price` : centimes HT, **`null` pour visiteur et PRO** (et pour une pièce plus commercialisée) ; `stock.level` : `en_stock`,
  `rupture` (pas de bouton « Ajouter ») ou `plus_commercialisee` (« Plus commercialisée ») si `enabled: false`.
- Ajout au panier (ECOM) : `POST /shop/orders/{token}/items {"productVariant": part.variant, "quantity": n}` (`variant` `null` = non commandable).

### `POST /shop/yack/sav/nameplate-readings` (photo de la plaque ou de l'étiquette, public)

`multipart/form-data`, champ `photo` : JPEG, PNG ou WebP (type lu sur le contenu), 20 Mo au plus. La photo n'est pas
conservée ; avec l'adapter réel, elle est réencodée sans EXIF avant l'envoi au modèle (ADR 0003). Numéro de série du parc
installé : `found`. Sinon, référence du modèle présente au catalogue : `product`, avec la fiche (`/produit/{slug}`) et les
noms de ses documents.

```bash
curl -s -H 'Accept: application/json' -F 'photo=@public/yack/demo/plaques/plaque-SRK25ZS-W-FICTIF.png' \
  http://localhost:8088/api/v2/shop/yack/sav/nameplate-readings
```
```json
200 {"status": "found", "serialNumber": "YK24HYD11S000321", "reference": "FDCW-VNX-A", "model": "Hydrolution", "product": null, "simulated": true}
200 {"status": "product", "serialNumber": "MH21ZSW0004242", "reference": "SRK25ZS-W", "model": "Mural 2,5 kW R32",
     "product": {"code": "SRK25ZS-W", "name": "SRK25ZS-W Mural - 2,5kW - R32", "slug": "srk25zs-w-mural-2-5kw-r32-srk25zs-w",
                 "brand": "MHI", "documents": ["Documentation Produit", "Notice diagnostic", "Notice d'utilisation", "Principe de maintenance",
                               "Principe de nettoyage & filtres", "DAP & DWG & BIM"]},
     "simulated": true}
200 {"status": "unknown", "serialNumber": "YK23EVO06M009999", "reference": "YK-PAE-M06", "model": "Evolys monobloc 6 kW", "product": null, "simulated": true}
200 {"status": "unreadable", "serialNumber": null, "reference": null, "model": null, "product": null, "simulated": true}
422 {"violations": [{"propertyPath": "photo", "message": "Format non accepté (JPEG, PNG ou WebP)."}]}
422 {"violations": [{"propertyPath": "photo", "message": "Photo HEIC non lue : envoyez-la en JPEG, PNG ou WebP."}]}
503 {"detail": "Lecture indisponible : saisissez le numéro de série"}
```
`found` : le front ouvre `/sav/{serialNumber}` (même page qu'une saisie). `simulated` : réponse de l'adapter de démonstration.

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
Paramètres facultatifs `sector`, `range`, `activity` (valeurs de `/filters` ; une valeur inconnue est ignorée, voir `criteria`).

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
# 5. paiement (choix : GET /shop/orders/$T/payments/{id}/methods ou /shop/payment-methods) : recalcule escompte + TVA
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

### Moyens de paiement : `escompteRate` / `escompteLabel`

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

### Commande validée : `GET /shop/orders/{token}` (extrait)

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
        "bank": "Banque Fictive du Sud, Aix-en-Provence",
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
- `paymentMethod.card` : `true` pour la carte bancaire.
- `paymentInstruction` (une fois la commande validée) : virement → `kind: "virement"`, RIB, référence `YACK-<n°>`, `dueDate` (`Y-m-d`), `paid`
  (paiement terminé) ; carte → `{"kind": "carte", "amount": 19910, "paid": true, "bankAccount": null, "reference": null, "dueDate": null, "card": {…}}`,
  `card` = dernière tentative (voir ci-dessous), `paid: false` tant que la carte n'est pas acceptée.
- `payable` : `{paymentId, amount, methodCode, lastAttempt, methods[]}` tant que la commande attend un paiement (sinon `null`) ; `lastAttempt` = tentative
  carte refusée ou annulée (`null` en virement) ; `methods[]` = moyens de paiement possibles (`code`, `name`, `escompteRate`, `escompteLabel`, `card`).
- `payments[]` : historique `{id, method{code,name}, state, stateLabel, amount, createdAt, updatedAt, card}` (une ligne par tentative).
- Tentative carte (`card`) : `{status (pending|accepted|refused|cancelled), message, card{brand, label, last4, expiry}, threeDSecure, oneClick,
  transactionReference, startedAt, concludedAt, cardSaved}` ; jamais de numéro complet.
- `paymentStatus` / `shippingStatus` : `{state, label}` (« Virement attendu », « Payée », « En préparation », « Expédiée »…).
- `shipments[]` : `{id, method{code,name}, state, tracking, shippedAt}` (n° de suivi saisi au back-office à l'expédition).
- `items[]` (panier et commande) : `{id, variantCode, productCode, productSlug, image, type, typeLabel}` par ligne, même `id` que
  `items[].id` natif : de quoi afficher vignette (URL absolue, filtre `sylius_shop_product_thumbnail`, `null` sans image), code, lien
  fiche et badge produit fini / pièce détachée sans appel catalogue supplémentaire.

### Paiement par carte (ADR 0009, client connecté)

La commande carte est validée « en attente de paiement » (e-mail de confirmation au paiement). Le front ouvre ensuite le paiement :

```bash
# ouverture → 201 {"sessionId": "…", "redirectUrl": "/paiement-securise/<session>"} ; avec une carte enregistrée : {"savedCardId": 1}
#   (redirectUrl = URL de retour Yack si aucun 3-D Secure n'est demandé) ; 409 si la commande n'attend pas de paiement carte
curl -s -X POST http://localhost:8088/api/v2/shop/yack/orders/$T/card-payment -H "Authorization: Bearer $ECOM" -H 'Content-Type: application/json' -d '{}'
# retour du client (/commande/retour-paiement/$T) → 200 {tokenValue, status, message, reason, card, threeDSecure, oneClick, transactionReference}
curl -s -X POST http://localhost:8088/api/v2/shop/yack/orders/$T/card-payment/result -H "Authorization: Bearer $ECOM"
# refus / annulation : bascule vers le virement (natif, escompte et TVA recalculés, e-mail de confirmation)
curl -s -X PATCH http://localhost:8088/api/v2/shop/account/orders/$T/payments/$PAYMENT_ID -H "Authorization: Bearer $ECOM" \
  -H 'Content-Type: application/merge-patch+json' -d '{"paymentMethod":"virement_15j"}'
# cartes enregistrées : GET /shop/yack/saved-cards → [{id, brand, brandLabel, last4, label, expiry, expired, createdAt}] ; DELETE /shop/yack/saved-cards/{id} → 204
```

Page de paiement simulée (publique, identifiant de session de 32 caractères hexadécimaux) : `GET /shop/yack/simulated-monext/sessions/{id}`
(`merchant`, `orderNumber`, `reference`, `amount`, `currencyCode`, `step` = `card|challenge|accepted|refused|cancelled`, `card`, `oneClick`,
`refusalReason`, `challengeCode`, `returnUrl`) ; `POST …/card` `{"brand":"visa","last4":"4242","expiryMonth":12,"expiryYear":2030,"saveCard":false}`
(forme masquée seulement, contrôlée par la page) ; `POST …/challenge` `{"code":"123456"}` ; `POST …/cancel`. 404 session inconnue, 409 étape terminée.

### Historique : `GET /shop/orders` (ECOM)

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

**Résolution** (`App\Yack\Content\Application\CmsTemplateResolver`, templates **publiés** uniquement : un template affecté mais en brouillon est ignoré, comme s'il n'était pas affecté) :
- produit → template du produit, sinon *template produits* de la catégorie principale puis de ses ancêtres, sinon défaut `product` ;
- catégorie → template de la catégorie, sinon de ses ancêtres, sinon défaut `taxon`.

**Affectations** (même donnée que le BO : champ « Template CMS » des formulaires produit et taxon) :
`sylius_product.cms_template_id` (template produit), `sylius_taxon.product_cms_template_id` (template produit des produits de la catégorie et de ses sous-catégories), `sylius_taxon.cms_template_id` (template catégorie de la page et de ses sous-catégories).

### Blocs

Blocs libres des pages (§ `BlockType` : `hero`, `texte-image`, `produits`, `categories`, `cta`, `faq`, `reassurance`, `chiffres-cles`, `acces-rapides`, `documents`, `tuiles`, `accompagnement`, les blocs de base `titre`, `texte`, `bouton`, `espace` et les conteneurs `section`, `colonnes`) autorisés partout, plus des **blocs liés** au contexte de la page (aucune prop de donnée : ils lisent le produit / la catégorie rendu), autorisés seulement dans un template du bon type.
Props : liste de valeurs (1re = défaut) ou texte libre (défaut `''` ; pour `title`, `''` = intitulé par défaut du bloc). Un bloc sans prop est renvoyé avec `"props": []` (tableau PHP vide) : le traiter comme `{}`.

| `type` | Contrat | Libellé | Props |
|---|---|---|---|
| `produit-galerie` | ProductGallery | Galerie produit | aucune |
| `produit-infos` | ProductInfo | Informations produit (nom, marque, gamme, code, description courte) | aucune |
| `produit-achat` | ProductBuyBox | Achat (prix, stock, panier), **obligatoire, unique** | aucune |
| `produit-caracteristiques` | ProductAttributes | Caractéristiques techniques | `title` |
| `produit-description` | ProductDescription | Description | `title` |
| `produit-documents` | ProductDocuments | Documents | `title` |
| `produit-sav` | ProductSavLink | Lien SAV | `title`, `text` |
| `produits-associes` | RelatedProducts | Produits associés | `title`, `limit` `4\|8` |
| `categorie-entete` | TaxonHeader | En-tête de catégorie | `description` `oui\|non` |
| `categorie-produits` | TaxonProductGrid | Liste des produits (filtres, tri, pagination), **obligatoire, unique** | `perPage` `9\|18\|27`, `sort` `position\|nom-asc\|nom-desc\|recent\|ancien` (tri initial) |
| `categorie-sous-categories` | TaxonChildren | Sous-catégories | `title` |

Validation à l'enregistrement (422, `propertyPath: "blocks"`) : bloc lié hors de son type, bloc obligatoire absent ou en double. Une page CMS (`/admin/cms-pages`) refuse tout bloc lié.

#### Blocs de base (`titre`, `texte`, `bouton`, `espace`)

Blocs libres, en tête de la rubrique « Mise en page » de la palette ; style complet ; textes écrits sur place dans le canevas (ADR 0010).

| `type` | Libellé | Props |
|---|---|---|
| `titre` | Titre | `titre` (obligatoire, une ligne, 200 caractères au plus), `sousTitre` (une ligne, 200 caractères au plus). Balise du titre : style `balise` (h2 par défaut) |
| `texte` | Texte | `texte` : texte simple multiligne, un paragraphe par groupe de lignes séparé par une ligne vide, retours à la ligne gardés, jamais de HTML |
| `bouton` | Bouton | `libelle` (une ligne, 200 caractères au plus), `url` (mêmes cibles que les autres liens : `page:<slug>`, chemin du site, http(s), mailto, tel), `variante` `plein\|contour\|lien`. Sans libellé ou sans lien valable : pas de bouton |
| `espace` | Espace | `hauteur` `m\|s\|l\|xl` (espacements de la charte), `filet` `non\|oui` |

```bash
# 422 « titre » est obligatoire (Titre vide), une ligne de 200 caractères au plus (titre, sousTitre, libelle),
#     lien refusé (javascript:, data:, //hôte…), variante, hauteur ou filet hors liste, prop inconnue
```

Rendu :
- rythme : un Titre garde 16 px avec le bloc suivant, un Texte 32 px, un Bouton 48 px, à la racine comme dans une section ou une colonne ; un Espace remplace l'écart : du bloc qui le précède à celui qui le suit, la distance est sa hauteur, partout ; un Titre, un Texte ou un Bouton en premier bloc d'une page commence 48 px sous l'en-tête ;
- Bouton `lien` : texte à l'accent, souligné au survol et au focus, aligné sur le texte voisin ;
- le style `accent` colore le bouton plein et contour et le filet de l'Espace ; le texte posé sur l'accent (bouton plein, survol du contour) est blanc ou noir selon la luminance relative de l'accent (WCAG, seuil 0,1791) : 4,5:1 au moins, au repos, au survol et à l'appui. Sans couleurs relatives CSS, ces surfaces gardent les couleurs de la charte ;
- Espace : hachuré, avec un contour pointillé, dans le canevas seulement.

#### Style d'un bloc (`style`)

`style` est un objet `{propriété: valeur}` à côté de `props` (`BlockStyle::PROPERTIES`, même liste dans `front/src/lib/blocks/style.ts`). Une propriété absente, ou vide, garde le rendu du bloc. Chaque valeur est une clé de la charte (`Charte`) ; seuls les couleurs, la taille du titre et les espacements acceptent aussi une valeur libre bornée, qui est un **écart de charte**. Une valeur libre égale à une valeur de la charte devient sa clé (`#82cec8` donne `teal`, `32px` donne `m`).

| Propriété | Valeurs | Écart de charte |
|---|---|---|
| `fond`, `texte`, `accent` | couleur de la charte (`magenta`, `magenta-dark`, `plum`, `teal`, `teal-dark`, `teal-deep`, `yellow`, `ochre`, `dark`, `gray`, `light`, `white`, `black`) ou `#rrggbb` | `#rrggbb` hors charte |
| `balise` | balise du titre principal : `h1`, `h2`, `h3`, `p`. Absente : la balise du bloc (h2 ; h1 pour le bandeau d'une page). Le titre garde l'aspect de la balise du bloc, sa taille reste celle de `titre` | jamais |
| `titre` | taille du titre : `s`, `m`, `l`, `xl`, ou px de 14 à 96 | px hors charte |
| `hautEspace`, `basEspace`, `gaucheEspace`, `droiteEspace` | espacement : `0`, `s`, `m`, `l`, `xl`, ou px de 0 à 200 | px hors charte |
| `largeur` | `contenu`, `large`, `pleine` | jamais |
| `alignement` | `gauche`, `centre`, `droite` | jamais |
| `alignementVertical` | `haut`, `milieu`, `bas` : alignement des deux colonnes d'un texte + image ou d'un bandeau. Absent : au milieu | jamais |
| `arrondi` | `aucun`, `petit`, `moyen`, `grand` | jamais |
| `masquerMobile`, `masquerOrdinateur` | `oui`, `non` | jamais |
| `ancre` | `[a-z0-9-]`, 40 caractères au plus | jamais |

Niveau du builder (`BuilderLevel`, par administrateur) : **Charte** (aucune valeur libre nouvelle : 422), **Libre avec avertissement** (valeur libre acceptée, bandeau « Hors charte » dans le builder), **Libre**. Toute autre valeur, ou une propriété inconnue : 422 quel que soit le niveau. Les écarts enregistrés sont listés dans le back-office (*Contenu → Écarts de charte*), où « Remplacer partout » les ramène à une valeur de la charte.

```bash
# niveau Charte (editeur@yack.test), {"type":"texte","props":{"texte":"Essai"},"style":{"gaucheEspace":"120px"}} :
# 422 {"violations":[{"propertyPath":"blocks","message":"Bloc n°1 : l'espacement 120px est hors charte. Votre compte ne permet pas de sortir de la charte."}], …}
# tout niveau, {"balise":"h4"} : 422 « Bloc n°1 : « h4 » n'est pas une valeur autorisée pour « Balise ». »
```

Le builder n'affiche pour un bloc que les propriétés de style qui changent son rendu ; l'API accepte toujours toutes les propriétés de `BlockStyle::PROPERTIES` (un conteneur : `BlockType::CONTAINER_STYLE`).

#### Conteneurs de mise en page (`section`, `colonnes`)

`blocks` est un arbre de deux niveaux au plus ; une page sans conteneur garde la forme plate (aucune migration). Deux blocs libres rangent les autres :

```json
[
  {"type": "hero", "props": {…}, "style": {}},
  {"type": "section", "props": {"audience": "tous"}, "style": {"fond": "light", "hautEspace": "l"},
   "children": [
     {"type": "colonnes", "props": {"ratio": "66-33", "alignement": "haut", "espacement": "m", "audience": "tous"}, "style": {},
      "columns": [
        [{"type": "faq", "props": {…}, "style": {}}],
        [{"type": "accompagnement", "props": {…}, "style": {}}]
      ]}
   ]}
]
```

| `type` | Contenu | Props | Style |
|---|---|---|---|
| `section` | `children` : des `colonnes` ou des blocs de contenu (pleine largeur compris) | `audience` | `fond`, `texte`, `hautEspace`, `basEspace`, `gaucheEspace`, `droiteEspace`, `largeur` `contenu\|large\|pleine` (`pleine` : bord à bord), `masquerMobile`, `masquerOrdinateur`, `ancre` |
| `colonnes` | `columns` : une liste de blocs par colonne, autant de listes que le ratio en demande | `ratio` `50-50\|33-66\|66-33\|33-33-33`, `alignement` `haut\|centre\|bas`, `espacement` `m\|0\|s\|l\|xl` (espacements de la charte), `audience` | idem `section` |

Règles (422, `propertyPath: "blocks"`, message qui nomme le bloc par son chemin, « Section 2 › colonnes 1 › colonne 2 › bloc 1 (FAQ) ») :
- racine > `section` > `colonnes` > colonne > bloc ; `colonnes` aussi directement à la racine ; une `section` ne se place qu'à la racine ;
- une colonne ne contient que des blocs de contenu libres hors pleine largeur (`hero`, `produits`, `categories`, `cta`, `chiffres-cles` et `tuiles` refusés) ;
- un bloc de contenu n'a ni `children` ni `columns` ; un bloc lié d'un template reste à la racine ;
- un conteneur vide est accepté ; au plus 30 blocs par page, conteneurs et blocs imbriqués compris ;
- les propriétés de style d'un conteneur se limitent à la colonne « Style » ci-dessus, avec les mêmes niveaux de builder que les blocs (un compte de niveau charte ne pose que des valeurs de la charte).

```bash
# 422 {"violations":[{"propertyPath":"blocks","message":"Section 1 › colonnes 1 › colonne 1 › section 1 : une section se place à la racine de la page, jamais dans une section ou une colonne."}], …}
# 422 {"violations":[{"propertyPath":"blocks","message":"Section 1 › colonnes 1 › colonne 1 › bloc 1 (Bandeau principal) : bloc pleine largeur, interdit dans une colonne."}], …}
# 422 {"violations":[{"propertyPath":"blocks","message":"Colonnes 1 : la répartition « 33-33-33 » demande 3 colonnes, 4 reçues."}], …}
```

Rendu (SSR et canevas) : la section pose son fond et ses espacements sur toute la largeur, les colonnes forment une grille aux ratios fixes, empilée sous 768px ; le filtre d'audience s'applique à chaque niveau.

### Shop : `GET /shop/cms-templates/resolve?type=product|taxon&code={code}` (public)

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

## 12. Assistant documentation de la fiche produit

Contexte `Documentation` (`src/Yack/Documentation/README.md`) : encart « Une question sur cet appareil ? » de
`/produit/<slug>`. Comptes connectés seulement (PRO et ECOM, `ROLE_USER`) : **401** pour un visiteur, sur les deux
routes. Le corpus du site est vide (aucune notice dans data/yack/demo/documentation) : toute couverture rend
`"notices": []` et toute question « Je ne trouve pas la réponse dans la documentation. ». Exemples ci-dessous écrits
d'après le code ; les réponses avec une notice sont relevées sur le corpus de test
(`tests/Yack/Documentation/Infrastructure/Corpus/notices-test`, notices FICTIF), pas sur la stack.

### `GET /shop/yack/documentation-coverages/{productCode}` (comptes connectés)

Notices d'un produit et question d'exemple de sa fiche. Code produit : lettres, chiffres et tirets, 64 caractères au
plus (sinon la route ne répond pas : 404) ; casse indifférente, rendu en capitales.

```json
200 {"productCode": "YK-PAE-M08", "notices": [], "exampleQuestion": null}
200 {"productCode": "YK-PAE-M08", "notices": ["Notice d'installation Evolys 8 kW (FICTIF)"],
     "exampleQuestion": "Quelle est la longueur maximale de liaison hydraulique ?"}
401 {"code": 401, "message": "JWT Token not found"}
```

Le premier 200 est celui du site (corpus vide), le second celui du corpus de test. Toujours 200 pour un compte
connecté : un produit sans notice donne `notices` vide et `exampleQuestion` null. `notices` : titres des notices,
c'est-à-dire les sources que citent les réponses. Le front n'affiche l'encart à un compte connecté que si `notices`
n'est pas vide (`showsPanel` de `front/src/lib/documentation/types.ts`).

### `POST /shop/yack/documentation-questions` (comptes connectés)

`{question, productCode?}` : `productCode` facultatif (question posée depuis une fiche : seules les notices de ce
produit sont lues).

```bash
curl -s -X POST http://localhost:8088/api/v2/shop/yack/documentation-questions -H "Authorization: Bearer $PRO" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"question":"Quelle est la longueur maximale de liaison hydraulique ?","productCode":"YK-PAE-M08"}'
```
```json
200 {"answer": "Je ne trouve pas la réponse dans la documentation.", "sources": [], "simulated": false}
200 {"answer": "La longueur maximale de la liaison hydraulique entre l'unité extérieure et le module intérieur est de 25 m en aller simple, …",
     "sources": [{"source": "Notice d'installation Evolys 8 kW (FICTIF)", "page": 12}], "simulated": true}
401 {"code": 401, "message": "JWT Token not found"}
422 {"violations": [{"propertyPath": "question", "message": "Votre question doit comporter au moins 3 caractères."}]}
422 {"violations": [{"propertyPath": "productCode", "message": "Code produit invalide."}]}
503 {"detail": "L'assistant documentation est indisponible pour le moment : réessayez dans quelques minutes."}
```

Le premier 200 est celui du site (corpus vide), le second celui du corpus de test (adapter de démonstration).

- `question` : 3 à 300 caractères, espaces regroupées (vide : « Écrivez votre question. ») ; `productCode` : lettres,
  chiffres et tirets, 64 caractères au plus.
- Déroulé : 3 passages au plus retrouvés (récupération lexicale, filtrés par `productCode`) ; rien de pertinent :
  « Je ne trouve pas… » sans appel au modèle ; sinon seuls ces passages vont à l'answerer, et toute source hors de ces
  passages est retirée (une source réécrite par le modèle, à la casse, aux accents, à la ponctuation ou aux espaces
  près, est ramenée au titre de la notice).
- `answer` : texte brut, à afficher comme du texte (jamais en HTML). Adapter de démonstration : le meilleur passage mot
  pour mot ; adapter réel (`YACK_AI_ADAPTER=anthropic`) : réponse rédigée à partir des seuls passages lus.
- `sources` : `{source, page}`, titre de la notice et page ; vide seulement pour « Je ne trouve pas la réponse dans la
  documentation. ».
- `simulated` : réponse de l'adapter de démonstration, à signaler (repère « démonstration ») ; faux pour « Je ne trouve
  pas… » quand aucun passage n'est retrouvé (aucun answerer appelé).
- 503 : modèle indisponible (plafond quotidien, quota, délai).

## 13. Manuels et notices d'un appareil

Contexte `Assistance` (`src/Yack/Assistance/README.md`, `docs/adr/0008-module-assistance.md`). Interrupteur
`YACK_ASSISTANCE` (bloc yack/assistance de `.env`, `1` par défaut ; compose passe la même valeur à l'API et au front,
couper : `YACK_ASSISTANCE=0 make yack-up`) : à `0`, toutes les routes de cette section répondent **404** (sauf à un
visiteur sur `POST …/questions` : le droit `ROLE_USER` de l'opération passe avant, il reçoit 401), et le front répond 404
sur `/assistance`. Les documents
s'ouvrent par leur lien vers le PDF officiel du fabricant (`url`, en https) ; aucun PDF n'est servi par Sylius.
Exemples ci-dessous écrits d'après le code et ses tests, documents de la recherche `SRK25ZS W` relevés sur la base livrée en appelant les cas d'usage hors Symfony (abrégés `…`), pas relevés sur la stack.

### `GET /shop/yack/assistance/manuals?q=` ou `?reference=&serial=` (public)

| Paramètre | Rôle |
|---|---|
| `q` | Saisie libre : n° de série (ou ServiceCode) du parc installé, sinon référence, sinon nom de produit. L'emporte sur les deux autres. |
| `reference` | Référence validée (écran « C'est bien ça ? »), cherchée comme une référence. |
| `serial` | N° de série validé. Connu du parc : ses produits finis sont cherchés ; inconnu : rendu tel quel, sans autre effet. |

Saisie libre, dans l'ordre : n° de série connu du parc ; sinon référence découpable (découpage MHI) ou présente dans la
base ; sinon mots cherchés dans le nom et le code des produits de la base (tous les mots, accents et casse ignorés) : les
8 premiers produits, dans l'ordre de la base, donnent `choices`, et `moreChoices` est vrai s'il y en a davantage ; sinon
`level: aucun`. Le n° de série ne sert jamais à deviner un modèle par son format.

```bash
curl -s -H 'Accept: application/json' 'http://localhost:8088/api/v2/shop/yack/assistance/manuals?q=SRK25ZS%20W'
```
```json
200 {
  "query": "SRK25ZS W",
  "reference": {"read": "SRK25ZS W", "canonical": "SRK25ZS-W", "structured": true, "corrected": false,
                "brand": "MHI", "brandSource": "catalogue"},
  "serial": null,
  "product": {"code": "SRK25ZS-W", "name": "SRK25ZS-W Mural - 2,5kW - R32", "slug": "srk25zs-w-mural-2-5kw-r32-srk25zs-w"},
  "level": "exact",
  "groups": [
    {"level": "exact", "reason": "reference_identique", "source": "reference", "documents": [
      {"id": "mhi-srk-zs-w-utilisation-rlf012a109j-fr", "title": "Manuel de l'utilisateur climatiseur (…)",
       "type": "utilisation", "typeLabel": "Manuel d'utilisation", "languages": ["fr"],
       "url": "https://www.mhi-mth.co.jp/en/products/pdf/rlf012a109j_french.pdf", "official": true,
       "manualNumber": "RLF012A109J", "year": "2023", "pages": 40, "models": ["SRK20ZS-W", "…", "SRK50ZS-WT"],
       "hasText": true, "indexedPages": "4-8, 10, 33-38"}, "…"]},
    {"level": "serie", "reason": "variante_suffixe", "source": "reference", "documents": ["…"]}
  ],
  "choices": [],
  "moreChoices": false,
  "askable": true
}
```
```json
GET ?q=YK19KAZ35S004567
200 {"query": "YK19KAZ35S004567", "reference": null,
     "serial": {"value": "YK19KAZ35S004567", "known": true, "savUrl": "/sav/YK19KAZ35S004567",
                "productCodes": ["SRK25ZS-WFT", "SRC25ZS-W2"], "conflict": false},
     "product": {"code": "SRK25ZS-WFT", …}, "level": "…", "groups": [{"…": "…", "source": "serial"}], "choices": [], "askable": …}
GET ?reference=SRK35ZS-W&serial=YK19KAZ35S004567
200 {"query": "SRK35ZS-W", "reference": {"canonical": "SRK35ZS-W", …, "brand": "MHI", "brandSource": "catalogue"},
     "serial": {"value": "YK19KAZ35S004567", "known": true, …, "conflict": true},
     "product": {"code": "SRK35ZS-W", …},
     "groups": [{"level": "exact", "source": "serial", …}, {"level": "exact", "source": "reference", …}, …]}
GET ?serial=ABC123456
200 {"query": "ABC123456", "reference": null,
     "serial": {"value": "ABC123456", "known": false, "savUrl": null, "productCodes": [], "conflict": false},
     "product": null, "level": "aucun", "groups": [], "choices": [], "askable": false}
GET ?q=mural%20titane   (9 produits de la base correspondent : les 8 premiers sont rendus)
200 {"query": "mural titane", "reference": null, "level": "aucun", "groups": [],
     "choices": [{"code": "SRK15ZS-WFT", "name": "Mural Titane Wifi intégré - 1,5kW - R32"}, …], "moreChoices": true,
     "askable": false}
GET ?q=
422 {"violations": [{"propertyPath": "q", "message": "Saisissez une référence, un modèle ou un n° de série."}]}
GET ?reference=<101 caractères>
422 {"violations": [{"propertyPath": "reference", "message": "Saisie trop longue : 100 caractères au plus."}]}
```

- `reference` : la référence comprise (`ModelReference`) : `read` tel que saisi, `canonical` (forme normalisée, corrigée si
  `corrected`), `structured` (découpée en préfixe, puissance, série, suffixe). Null pour un n° de série seul, un choix de
  modèles ou une phrase sans résultat (`query` reste la saisie). `brandSource` : `catalogue` (produit de la base ou fiche
  du catalogue), `deduite` (préfixe de la référence, à afficher « déduite de la référence ») ou `null`.
- `serial` : `value` en majuscules sans espaces ; `known` : présent dans le parc installé ; `savUrl` (vues éclatées et
  garantie) seulement s'il est connu ; `productCodes` : produits finis de l'appareil (vide s'il est inconnu) ; `conflict` :
  une référence est aussi saisie et n'est aucun de ces produits (bandeau « Le n° de série correspond à …, la référence
  saisie est … : vérifiez la plaque »).
- `product` : fiche du catalogue (`/produit/{slug}`) de la référence, sinon du premier produit fini du parc qui en a une.
- `groups` : du meilleur au moins bon niveau (`exact`, `serie`, `famille`), puis par raison (`reference_identique`,
  `variante_suffixe`, `autre_puissance`, `meme_type_appareil`), puis par source. `source` : `reference` (documents de la
  référence saisie) ou `serial` (documents des produits finis du n° de série). Les deux sources coexistent seulement si
  `conflict` est vrai ; sans conflit, un n° de série connu donne les groupes `serial` seuls (la référence est l'un de ses
  produits). Un document n'apparaît qu'une fois par source, à son meilleur niveau ; le niveau `famille` (5 documents au
  plus, utilisation, télécommande, brochure) seulement si la source n'a aucun document exact ou série. Dans un groupe :
  type (installation, utilisation, technique, service, fiche technique, pièces, télécommande, brochure,
  autre), français d'abord, officiel d'abord, année décroissante, titre.
- `level` : niveau du meilleur groupe, `aucun` sans groupe. `choices` : `{code, name}`, de 1 à 8, avec `groups` vide.
  `moreChoices` : d'autres produits correspondent au-delà des 8 choix rendus (afficher « Précisez la puissance ou la
  référence pour affiner »), faux sinon.
- Document : `hasText` vrai quand son texte est indexé pour les questions ; `indexedPages` : pages du PDF indexées, telles
  que dans la base (« 4-8, 10, 33-38 » : liste et plages), à afficher sous le panneau des questions (« Pages indexées :
  … ») ; null sans texte indexé.
- `askable` : au moins un document `exact` ou `serie` a un texte indexé (`hasText`).

### `POST /shop/yack/assistance/plate-readings` (photo de la plaque, public)

`multipart/form-data`, champ `photo` : JPEG, PNG ou WebP (type lu sur le contenu), 20 Mo au plus. Rend ce qui est lu
sur la plaque pour l'écran de validation (« C'est bien ça ? ») et **ne cherche aucun document** : la recherche
(`GET …/manuals?reference=&serial=`) n'est lancée qu'avec ce que la personne a confirmé ou corrigé. La photo n'est pas
conservée ; avec l'adapter réel, elle est réencodée sans EXIF avant l'envoi au modèle (ADR 0003). Même lecture que
`POST /shop/yack/sav/nameplate-readings` (§7), sans la recherche dans le parc ni dans le catalogue.

```bash
curl -s -H 'Accept: application/json' -F 'photo=@public/yack/demo/plaques/plaque-SRK25ZS-W-FICTIF.png' \
  http://localhost:8088/api/v2/shop/yack/assistance/plate-readings
```
```json
200 {"serialNumber": "MH21ZSW0004242", "referenceRead": "SRK25ZS-W", "reference": "SRK25ZS-W", "model": "Mural 2,5 kW R32",
     "brand": "MHI", "brandSource": "plaque", "simulated": true}
200 {"serialNumber": "MH22ZSW0003535", "referenceRead": "SRK35ZS-W", "reference": "SRK35ZS-W", "model": "Mural 3,5 kW R32",
     "brand": "MHI", "brandSource": "deduite", "simulated": true}
200 {"serialNumber": null, "referenceRead": "SRK2SZS-W", "reference": "SRK25ZS-W", "model": null,
     "brand": "MHI", "brandSource": "deduite", "simulated": false}
200 {"serialNumber": null, "referenceRead": null, "reference": null, "model": null, "brand": null, "brandSource": null, "simulated": true}
422 {"violations": [{"propertyPath": "photo", "message": "Photo obligatoire."}]}
422 {"violations": [{"propertyPath": "photo", "message": "Format non accepté (JPEG, PNG ou WebP)."}]}
503 {"detail": "Lecture indisponible : saisissez la référence"}
```

Plaques de démonstration (adapter `YACK_AI_ADAPTER=fake`, dossier `public/yack/demo/plaques/`) : `plaque-SRK25ZS-W-FICTIF.png`
(marque lue), `plaque-sans-marque-FICTIF.png` (marque déduite de la référence), `plaque-illisible-FICTIF.png` (tous les
champs à `null`). La troisième réponse ci-dessus illustre une lecture réelle où un 5 est lu S.

- `serialNumber` : n° de série lu, en majuscules sans espaces ni tirets ; null s'il n'est pas lu ou ne ressemble pas à un
  n° de série. Il n'est pas cherché dans le parc à cette étape.
- `referenceRead` : la référence telle que lue ; `reference` : sa forme canonique (mêmes règles que `reference.canonical`
  de la recherche, correction des confusions de lecture comprise), null si rien d'exploitable n'est lu. Quand les deux
  diffèrent, l'écran affiche « lu : SRK2SZS-W, compris : SRK25ZS-W ».
- `model` : désignation lue, en clair.
- `brand`, `brandSource` : `plaque` (marque imprimée sur la plaque, écrite comme au catalogue quand elle le désigne :
  `YACK` donne `Yack`, `Mitsubishi Heavy Industries` donne `MHI` ; une autre marque reste telle que lue), `deduite`
  (préfixe de la référence, à afficher « déduite de la référence ») ou `null`.
- Tous les champs à `null` (sauf `simulated`) : plaque illisible ; le front ouvre quand même l'écran de validation, champs
  vides, avec « Plaque illisible : saisissez la référence ». 503 : même écran, avec le message reçu.
- `simulated` : réponse de l'adapter de démonstration.

### `POST /shop/yack/assistance/questions` (question aux manuels, comptes connectés)

`{question, reference}` : question posée aux manuels du modèle `reference` (la référence validée de la recherche). Seuls
les documents de niveau `exact` ou `serie` qui ont un texte indexé (`hasText`) sont lus ; `famille` et `aucun` ne
nourrissent jamais une réponse. Comptes connectés (PRO et ECOM) : **401** pour un visiteur. Moteur : celui du contexte
Documentation (récupération lexicale, 3 passages au plus, sources limitées aux passages lus), voir
`src/Yack/Assistance/README.md` (« Questions aux manuels »).

```bash
curl -s -X POST http://localhost:8088/api/v2/shop/yack/assistance/questions -H "Authorization: Bearer $PRO" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"question":"Que signifie le code erreur E 16 ?","reference":"SRK25ZS-W"}'
```
```json
200 {"answer": "Indoor unit PCB is faulty | … Indoor fan | … ON | E16 | … motor error | … Attention : passage tiré d'un tableau du manuel, dont les lignes et les colonnes sont mélangées dans le texte indexé. Vérifiez la valeur sur le PDF, à la page citée.",
     "sources": [{"documentId": "mhi-src-srk-zs-w-technique-22-srk-t-359", "title": "Technical Manual Inverter Wall Mounted Type Residential Air-Conditioners (Split system, air to air heat pump type)",
                  "page": 82, "url": "https://documents.unidomo.de/de/Installationsanleitung/Mitsubishi/22-SRK-T-359.pdf"}],
     "simulated": true}
{"question": "Comment nettoyer le filtre ?", "reference": "SRK25ZS-W"}
200 {"answer": "Ôtez le filtre photocatalytique désodorisant lavable (orange) du porte-filtre et inspectez le filtre. …",
     "sources": [{"documentId": "mhi-srk-zs-w-utilisation-rlf012a109j-fr", "title": "Manuel de l'utilisateur climatiseur (…)", "page": 34,
                  "url": "https://www.mhi-mth.co.jp/en/products/pdf/rlf012a109j_french.pdf"}], "simulated": true}
{"question": "Quelle est la capitale du Portugal ?", "reference": "SRK25ZS-W"}
200 {"answer": "Je ne trouve pas la réponse dans les manuels indexés de ce modèle.", "sources": [], "simulated": false}
{"question": "Comment nettoyer le filtre ?", "reference": "FDC224KXZME1"}
200 {"answer": "Je ne trouve pas la réponse : aucun manuel de ce modèle n'est indexé pour les questions. Les documents de la liste s'ouvrent en PDF.",
     "sources": [], "simulated": false}
401   (visiteur)
422 {"violations": [{"propertyPath": "question", "message": "Votre question doit comporter au moins 3 caractères."}]}
422 {"violations": [{"propertyPath": "reference", "message": "Référence obligatoire."}]}
503 {"detail": "Les manuels ne peuvent pas répondre pour le moment : réessayez dans quelques minutes."}
```

Réponses ci-dessus produites par l'adapter de démonstration sur les vrais textes indexés (relevées par les tests, pas sur
la stack) et abrégées (`…`).

- `question` : 3 à 300 caractères (espaces regroupées) ; `reference` : requise, 40 caractères au plus.
- `answer` : texte brut, à afficher comme du texte (jamais en HTML). Avec l'adapter de démonstration, c'est le meilleur
  passage mot pour mot (souvent en anglais pour le manuel technique) ; avec l'adapter réel, une réponse rédigée en
  français à partir des seuls passages lus. Une source tirée d'un tableau du manuel ajoute l'avertissement « Attention :
  passage tiré d'un tableau… ».
- `sources` : `documentId` (id de la recherche), `title`, `page` (page du PDF), `url` (PDF officiel, sans ancre : le front
  ouvre `url#page=N`, ignoré par Safari iOS, qui ouvre la page 1). Vide seulement pour les deux réponses sans source :
  « Je ne trouve pas la réponse dans les manuels indexés de ce modèle. » (manuels lus, rien de pertinent) et « Je ne
  trouve pas la réponse : aucun manuel de ce modèle n'est indexé… » (rien à lire : aucun modèle n'est appelé).
- `simulated` : réponse de l'adapter de démonstration (`YACK_AI_ADAPTER=fake`) ; faux pour les deux réponses sans
  source, qui n'appellent aucun answerer.
