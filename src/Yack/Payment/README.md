# Contexte Payment : escompte, modalités de paiement, instructions de paiement

## Rôle

Règles de paiement de Yack autour du tunnel natif de Sylius :

- escompte (remise pour paiement anticipé, en % du HT) par moyen de paiement : `Domain/PaymentMethodEscompteRates`, paramètre `app.yack.payment_method_escompte_rates` de `config/packages/yack.yaml` (un moyen absent retombe sur l'escompte du client, calculé par `src/OrderProcessing/EscompteProcessor.php`) ;
- modalités : virement (RIB de Yack, référence, échéance = date de commande + N jours) ou carte, `Domain/PaymentTerms`, `Domain/PaymentKind` (`virement`, `carte`), `Domain/BankAccount` (paramètres `app.yack.payment_terms.*`) ;
- instruction de paiement d'une commande passée : `Application/OrderPaymentInstruction` rend un `Domain/PaymentInstruction` (`null` tant que la commande n'a pas de numéro), affiché sur la page de confirmation, dans l'e-mail de confirmation et dans le détail de commande.

Le « RIB » de ce contexte est celui de Yack, affiché au client qui paie par virement. Aucun RIB client n'est demandé ni stocké.

## Ports et adapters

Pas de port à ce jour : la configuration est lue depuis `config/packages/yack.yaml`.

| Adapter (`Infrastructure/`) | Rôle |
|---|---|
| `Api/YackOrderNormalizer` | Bloc `yack` du panier et de la commande de l'API shop : détail des totaux (HT, escompte, port, TVA, TTC), moyen choisi, instruction de paiement, suivi |
| `Api/YackPaymentMethodNormalizer` | `escompteRate` et `escompteLabel` des moyens de paiement |
| `Card/FakeCardPaymentListener` | Paiement carte accepté immédiatement à la validation (simulé) |
| `Twig/PaymentInstructionExtension` | `yack_payment_instruction(order)` pour les e-mails et le back-office |

## Interfaces exposées aux autres contextes

- `Domain/PaymentMethodEscompteRates` : lu par `src/OrderProcessing/EscompteProcessor.php` (hors `src/Yack`).
- Lecture faite dans un autre contexte : `Catalog\Infrastructure\Api\YackProductNormalizer` (type de produit des lignes de commande), **écart connu à l'ADR 008**, résorbé par le ticket L0-02.

## Ressources API

Pas de ressource propre : le contexte enrichit les ressources natives de l'API shop.

| Méthode | Route | Apport Yack |
|---|---|---|
| GET | `/api/v2/shop/orders/{token}` | Bloc `yack` (totaux, escompte, instruction de paiement, suivi) |
| GET | `/api/v2/shop/payment-methods`, `/shop/orders/{token}/payments/{id}/methods` | `escompteRate`, `escompteLabel` |

Contrat détaillé : `docs/API_FRONT.md` §9 ; règles d'escompte : `docs/escompte.md`. Front : routes `panier`, `commande`, `compte/commandes`.

## Ce qui est simulé

- Carte bancaire : `FakeCardPaymentListener`, aucun prestataire de paiement, aucun débit. Une vraie intégration le remplace par le flux de demande de paiement et de notification du prestataire.
- RIB de Yack fictif (configuration).
