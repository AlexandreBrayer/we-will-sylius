# Contexte Installer : annuaire des installateurs partenaires

## Rôle

Page « Trouver un installateur » : rechercher les installateurs partenaires de Yack par secteur, gamme et activité (`Domain/Activity` : `installation`, `maintenance`, `depannage`), avec les marqueurs de carte.

- Modèle : `Domain/Installer`, critères `Domain/InstallerCriteria` (une valeur de filtre inconnue est ignorée).

## Ports et adapters

| Port (`Domain/`) | Adapter (`Infrastructure/`) | Rôle |
|---|---|---|
| `InstallerDirectory` | `Directory/InMemoryInstallerDirectory` | Réseau des installateurs (recherche, secteurs, gammes) |

Autre adapter : `Api/InstallerProvider` (fournisseur API Platform).

## Interfaces exposées aux autres contextes

Aucune. Le contexte ne lit aucun autre contexte.

## Ressources API

| Méthode | Route | Effet |
|---|---|---|
| GET | `/api/v2/shop/yack/installers?sector=&range=&activity=` | Installateurs correspondants, marqueurs de carte compris |
| GET | `/api/v2/shop/yack/installers/filters` | Valeurs des trois filtres |

Contrat détaillé : `docs/API_FRONT.md` §8. Front : route `trouver-un-installateur`.

## Ce qui est simulé

`InMemoryInstallerDirectory` : installateurs fictifs écrits dans le code. L'adapter réel lira le CRM ou l'ERP de Yack, même interface.
