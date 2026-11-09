# Contexte Payment : escompte, modalités de paiement, paiement par carte

## Rôle

Règles de paiement de Yack autour du tunnel natif de Sylius :

- escompte (remise pour paiement anticipé, en % du HT) par moyen de paiement : `Domain/PaymentMethodEscompteRates`, paramètre `app.yack.payment_method_escompte_rates` de `config/packages/yack.yaml` (un moyen absent retombe sur l'escompte du client, calculé par `src/OrderProcessing/EscompteProcessor.php`) ;
- modalités : virement (RIB de Yack, référence, échéance = date de commande + N jours) ou carte, `Domain/PaymentTerms`, `Domain/PaymentKind` (`virement`, `carte`), `Domain/BankAccount` (paramètres `app.yack.payment_terms.*`) ;
- instruction de paiement d'une commande passée : `Application/OrderPaymentInstruction` rend un `Domain/PaymentInstruction` (`null` tant que la commande n'a pas de numéro), affiché sur la page de confirmation, dans l'e-mail de confirmation et dans le détail de commande ;
- paiement par carte d'une commande passée (ADR `docs/adr/0009-paiement-carte-prestataire-simule.md`) : session chez le prestataire (page de paiement hébergée ou carte enregistrée), résultat appliqué au paiement Sylius, cartes enregistrées du client.

Le « RIB » de ce contexte est celui de Yack, affiché au client qui paie par virement. Aucun RIB client n'est demandé ni stocké.

## Paiement par carte

1. La commande est validée avec la carte bancaire : paiement Sylius « en attente » (`new`), commande « en attente de paiement ». L'e-mail de confirmation attend le résultat (`Infrastructure/Mailer/CardPaymentAwareOrderEmailManager`).
2. `Application/Card/StartCardPayment` ouvre une session chez le prestataire (`Domain/Card/CardPaymentGateway`) et note la tentative dans les détails du paiement (`Domain/Card/CardPaymentAttempt`, clé `yack_card`). Le client part vers l'URL de la session : la page de paiement hébergée (`/paiement-securise/{session}`), ou directement le retour Yack pour une carte enregistrée sans 3-D Secure.
3. Au retour (`/commande/retour-paiement/{token}`), `Application/Card/ConcludeCardPayment` lit le résultat et applique la transition Sylius : `complete` (commande payée, carte enregistrée si demandé, e-mail de confirmation), `fail` ou `cancel`. Après un refus ou une annulation, Sylius ajoute un nouveau paiement en attente : la commande reste payable (`Application/Card/PayableOrder`).
4. Commande non payée : nouvel essai par carte, ou bascule vers un virement par l'opération native de changement de moyen de paiement ; `Infrastructure/Sylius/RepricingPaymentMethodChanger` recalcule alors l'escompte, la TVA et le montant du paiement (`Infrastructure/Sylius/PlacedOrderRecalculator`) et envoie l'e-mail de confirmation quand la carte laisse place au virement.

Données de carte : seules la marque, les 4 derniers chiffres et l'expiration (`Domain/Card/MaskedCard`) atteignent Sylius ; le numéro complet et le cryptogramme restent sur la page de paiement. Une carte enregistrée (`src/Entity/Payment/SavedCard.php`, table `yack_saved_card`) garde en plus le jeton du prestataire (`Domain/Card/WalletCard`).

## Ports et adapters

| Port | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `Domain/Card/CardPaymentGateway` | `Card/Simulated/SimulatedMonextGateway` | Prestataire de paiement par carte : ouverture de session, lecture du résultat. Simulé d'après Monext / Payline, sans clé ni appel externe |
| `Domain/Card/SavedCards` | `Persistence/DoctrineSavedCards` | Cartes enregistrées d'un client (une seule par marque, 4 derniers chiffres et expiration) |

| Adapter (`Infrastructure/`) | Rôle |
|---|---|
| `Api/YackOrderNormalizer` | Bloc `yack` du panier et de la commande de l'API shop : détail des totaux (HT, escompte, port, TVA, TTC), moyen choisi, instruction de paiement, paiement attendu, historique des paiements, suivi |
| `Api/YackPaymentMethodNormalizer` | `escompteRate` et `escompteLabel` des moyens de paiement |
| `Api/CardPaymentOutcome`, `Api/StartCardPaymentProcessor`, `Api/CardPaymentResultProcessor` | Ouverture d'un paiement carte et lecture de son résultat (client connecté, sa commande) |
| `Api/SavedCardView`, `Api/SavedCardProvider`, `Api/SavedCardDeleteProcessor` | Cartes enregistrées du client connecté |
| `Api/SimulatedPaymentPageView`, `Api/SimulatedPaymentPageProvider`, `Api/SimulatedPaymentPageProcessor` | Opérations de la page de paiement hébergée simulée (lecture, carte masquée, code 3-D Secure, abandon) |
| `Card/Simulated/SimulatedScenario` | Résultat d'une carte de test selon ses 4 derniers chiffres |
| `Card/Simulated/SimulatedSessions` | Sessions du simulateur (pool de cache `yack.card_payment_simulator`, 24 h) |
| `Mailer/CardPaymentAwareOrderEmailManager` | E-mail de confirmation d'une commande carte envoyé au paiement, pas à la validation |
| `Sylius/RepricingPaymentMethodChanger`, `Sylius/PlacedOrderRecalculator` | Changement de moyen de paiement d'une commande passée : escompte, TVA et montant recalculés |
| `Twig/PaymentInstructionExtension` | `yack_payment_instruction(order)` et `yack_card_payment(payment)` pour les e-mails et le back-office |

## Interfaces exposées aux autres contextes

- `Domain/PaymentMethodEscompteRates` : lu par `src/OrderProcessing/EscompteProcessor.php` (hors `src/Yack`).
- Lecture faite dans un autre contexte : `Catalog\Application\ProductKindResolverInterface` (type de produit des lignes de commande, ADR 008).

## Ressources API

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/v2/shop/orders/{token}` | Bloc `yack` (totaux, escompte, instruction de paiement, paiement attendu, historique des paiements, suivi) |
| GET | `/api/v2/shop/payment-methods`, `/shop/orders/{token}/payments/{id}/methods` | `escompteRate`, `escompteLabel` |
| PATCH | `/api/v2/shop/account/orders/{token}/payments/{id}` | Natif Sylius, décoré : changement de moyen de paiement d'une commande en attente, totaux recalculés |
| POST | `/api/v2/shop/yack/orders/{token}/card-payment` | Ouvre un paiement carte (`savedCardId` facultatif) : `sessionId`, `redirectUrl` |
| POST | `/api/v2/shop/yack/orders/{token}/card-payment/result` | Applique et rend le résultat de la session en cours |
| GET, DELETE | `/api/v2/shop/yack/saved-cards`, `/api/v2/shop/yack/saved-cards/{id}` | Cartes enregistrées du client |
| GET | `/api/v2/shop/yack/simulated-monext/sessions/{id}` | Page de paiement simulée (public) |
| POST | `/api/v2/shop/yack/simulated-monext/sessions/{id}/card`, `…/challenge`, `…/cancel` | Carte masquée, code 3-D Secure, abandon (public) |

Contrat détaillé : `docs/API_FRONT.md` §9 ; règles d'escompte : `docs/escompte.md`. Front : routes `panier`, `commande`, `compte/commandes`, `compte/cartes`, `paiement-securise`.

## Ce qui est simulé

- Prestataire de paiement par carte : `SimulatedMonextGateway`, aucun débit. Cartes de test et parcours de démonstration : `docs/POC_YACK.md`. Une vraie intégration écrit un adapter Monext du port `CardPaymentGateway` (ADR 0009).
- RIB de Yack fictif (configuration).
