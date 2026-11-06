# Mécanique d'escompte

## Principe

Un escompte est une remise accordée à un client B2B s'il règle sa commande dans un délai défini.  
Il est calculé sur le **montant HT** de la commande et appliqué comme un ajustement négatif.

---

## Configuration par client (back-office)

Dans **Admin → Clients → [client] → onglet Général** :

| Champ | Description |
|-------|-------------|
| **Taux d'escompte (%)** | Taux appliqué sur le HT (ex: `5` pour 5 %) |
| **Délai d'escompte (jours)** | Nombre de jours pour bénéficier de la remise (ex: `20`) |

Un client sans taux ne bénéficie d'aucun escompte. Les deux champs doivent être renseignés.

---

## Fonctionnement technique

### Pendant le checkout (`EscompteProcessor`)

Priorité **5** dans le pipeline OrderProcessor (après les taxes, avant le paiement).

- Lit le `escompteRate` et `escompteDays` du client
- Vérifie que `order.created_at` est dans les N derniers jours
- Si valide : ajoute un ajustement de type `escompte` (montant négatif sur le HT)
- Si expiré ou absent : supprime l'ajustement existant

### Affichage shop

La ligne escompte apparaît dans le récapitulatif de commande (panier, checkout, compte client).

---

## Commande cron — Expiration des escomptes

Pour les commandes déjà passées en état `awaiting_payment`, un cron doit tourner régulièrement pour supprimer l'escompte des commandes dont le délai est dépassé.

```bash
# En production (crontab)
0 6 * * * php /srv/sylius/bin/console app:escompte:expire --env=prod
```

---

## Commandes Make disponibles

### Simuler un délai dépassé (pour les tests)

Recule le `created_at` d'une commande pour déclencher l'expiry :

```bash
# Recule created_at de 30 jours (défaut)
make escompte-simulate ORDER=000000025

# Avec un nombre de jours précis
make escompte-simulate ORDER=000000025 DAYS=25
```

### Lancer l'expiry

```bash
# Dry-run : voir ce qui serait traité, sans modifier
make escompte-expire-dry

# Application réelle : supprime les ajustements expirés
make escompte-expire
```

---

## Workflow de test complet

```bash
# 1. Dans le BO, configurer le client avec escompteRate=5 et escompteDays=20

# 2. Passer une commande avec ce client → vérifier la ligne escompte en front

# 3. Simuler l'expiration
make escompte-simulate ORDER=000000025 DAYS=25

# 4. Vérifier que la commande serait traitée
make escompte-expire-dry

# 5. Appliquer
make escompte-expire

# 6. Recharger la page de commande → la ligne escompte a disparu
```

---

## Fichiers concernés

| Fichier | Rôle |
|---------|------|
| `src/Entity/Customer/Customer.php` | Champs `escompteRate` + `escompteDays` |
| `src/OrderProcessing/EscompteProcessor.php` | Calcul et application de l'ajustement |
| `src/Form/Extension/AdminCustomerTypeExtension.php` | Champs dans le formulaire admin |
| `src/Command/ExpireEscompteCommand.php` | Commande Symfony `app:escompte:expire` |
| `config/packages/_sylius.yaml` | Hooks twig (admin + shop) |
| `migrations/Version20260407125927.php` | Migration `escompteRate` |
| `migrations/Version20260407140554.php` | Migration `escompteDays` |
| `bin/escompte-simulate-expired.sh` | Script de simulation |
| `bin/escompte-expire.sh` | Script de lancement du cron |
