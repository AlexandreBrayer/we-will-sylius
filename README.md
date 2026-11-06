# yack poc sylius + cms

POC PURJUS pour la refonte de l'eshop B2B Yack : back Sylius 2 (API Platform + back-office) et front headless SvelteKit (boutique + builder CMS GrapesJS).

## Démarrage

```bash
make yack-reset   # base vierge, migrations, fixtures Yack, clés JWT, dépendances du front
make yack-up      # boutique sur http://localhost:8088
```

| Accès | URL | Identifiants |
|---|---|---|
| Boutique | http://localhost:8088/ | `ecom@yack.test`, `pro@yack.test`, `pro2@yack.test` / `yack` |
| Back-office | http://localhost:8088/admin/ | `admin@yack.test` / `yack` |
| Builder CMS | http://localhost:8088/cms-admin/builder | `admin@yack.test` / `yack` |
| E-mails (MailHog) | http://localhost:8025/ | — |

## Documentation

- [docs/POC_YACK.md](docs/POC_YACK.md) : architecture, script de démo, n° de série, réel vs simulé, limites
- [docs/API_FRONT.md](docs/API_FRONT.md) : contrat de l'API boutique utilisée par le front
- [front/README.md](front/README.md) : app SvelteKit (BFF, routes, conventions)

Basé sur [Sylius Standard Edition](https://github.com/Sylius/Sylius-Standard).
