# Contexte Backoffice : habillage du back-office Sylius

## Rôle

Ce qui sépare le back-office Yack du back-office Sylius par défaut, côté PHP : les pictogrammes du menu. Aucune règle métier ici. Le reste de l'habillage (couleurs, typographie, cadre, tableau de bord) est dans `assets/admin/styles/`, `templates/admin/` et `templates/bundles/SyliusAdminBundle/` (maquette « Admin Sylius » du Figma YACK Seduction, nœud 23:3) : voir « Habillage du back-office » dans `docs/POC_YACK.md`.

## Ports et adapters

Aucun port : le contexte n'expose rien aux autres.

| Adapter (`Infrastructure/`) | Rôle |
|---|---|
| `Menu/AdminMenuIconListener` | Écouteur de `sylius.menu.admin.main` (priorité -10, après les autres) : donne à chaque groupe du menu le pictogramme de la maquette |

## Ce qui est simulé

Rien.

## Supprimer le contexte

Retirer `src/Yack/Backoffice/` rend au menu les pictogrammes de Sylius ; rien d'autre ne casse.
