# Contexte Account : profils, droits commerciaux, inscription, ouverture à la commande

## Rôle

Dire qui navigue et ce qu'il a le droit de faire. Règle du cahier des charges Yack : seuls les clients **ECOM** voient les prix et commandent en ligne ; un client **PRO** (installateur) voit le catalogue sans prix et peut demander l'ouverture à la commande ; un **visiteur** doit se connecter.

- Profils : `Domain/CustomerProfile` (`visitor`, `pro`, `ecom`), déduit du groupe client Sylius par `Infrastructure/Security/CustomerProfileResolver`.
- Droits : `Domain/CommercePermission` (`YACK_SEE_PRICES`, `YACK_PLACE_ORDER`, `YACK_REQUEST_ORDER_ACCESS`), tranchés par `Infrastructure/Security/CommercePermissionVoter`.
- Inscription : `Application/Registration/RegisterCustomer` (client, utilisateur créé **désactivé**, adresses, groupe `pro` ou `ecom`) ; l'e-mail d'activation part par l'événement Sylius `sylius.customer.post_register`.
- SIRET : `Domain/Siret` (14 chiffres, clé de Luhn ; règle particulière de La Poste), contrainte `Infrastructure/Validator/ValidSiret`.
- Demandes d'ouverture à la commande (PRO vers ECOM) : entité `src/Entity/Account/OrderAccessRequest.php`, statut `Domain/OrderAccessRequestStatus`, décision `Application/OrderAccessDecision` (acceptée = passage dans le groupe ECOM et e-mail `yack_order_access_granted`).

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `OrderAccessRequests` | `Persistence/DoctrineOrderAccessRequests` | Stockage des demandes d'ouverture |
| `ProSignups` | `Persistence/DoctrineProSignups` | Comptes PRO récents (tableau de bord du back-office) |
| `OrderAccessNotifier` | `Mailer/SyliusMailerOrderAccessNotifier` | E-mail « Votre compte est ouvert à la commande » |

Autres adapters : `EventListener/CommerceAccessListener` (panier, tunnel et commandes de l'API shop fermés aux non-ECOM : 401 anonyme, 403 PRO), `Api/PriceHidingProductVariantNormalizer` (prix à `null` pour visiteur et PRO, décore `sylius_api.normalizer.product_variant`), back-office (`Controller/Admin/OrderAccessRequestAdminController`, `Menu/AdminMenuListener`, `Twig/AdminAccountExtension`), fixture `Fixture/YackCustomerCompanyFixture` (raison sociale, SIRET, adresse des clients de démo).

## Interfaces exposées aux autres contextes

- `Domain/CommercePermission` : donnée lisible par tous (attribut de sécurité passé à `isGranted()`), utilisée par AfterSales pour masquer les prix des pièces.
- `Domain/CustomerProfile` : donnée (profil commercial).
- Aucun service d'`Application` n'est appelé par un autre contexte à ce jour.

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/yack/me` | Profil courant et droits (`canSeePrices`, `canOrder`, `canRequestOrderAccess`), public |
| POST | `/api/v2/shop/yack/registrations` | Inscription (compte désactivé, e-mail d'activation) |
| POST | `/api/v2/shop/yack/activations/{token}` | Activation par le lien de l'e-mail (204, 404 si jeton inconnu ou utilisé) |
| GET | `/api/v2/shop/yack/order-access-requests` | Demandes du client, la plus récente d'abord |
| GET | `/api/v2/shop/yack/order-access-requests/draft` | Formulaire prérempli (PRO) |
| POST | `/api/v2/shop/yack/order-access-requests` | Nouvelle demande (PRO, 409 si une demande est en attente) |
| GET | `/api/v2/shop/yack/order-access-requests/{id}` | Une demande du client |

Back-office : `/admin/yack/demandes-acces-commande` (liste, détail, accepter, refuser). Contrat détaillé : `docs/API_FRONT.md` §1 à §3, §5 et §6. Front : routes `connexion`, `inscription`, `activation`, `mot-de-passe`, `compte`.

## Ce qui est simulé

- SIRET contrôlé par sa clé seulement (pas d'appel à la base Sirene).
- Pas de notification à Yack d'une nouvelle inscription ou d'une nouvelle demande ; e-mails capturés par MailHog.
- Clients, sociétés et SIRET des fixtures fictifs.

## Tests

`tests/Yack/Account/Domain/SiretTest.php`.
