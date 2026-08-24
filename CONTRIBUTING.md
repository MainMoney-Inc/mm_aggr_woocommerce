# Contributing

This document is for people who change **this repository**. To install the
package into an application, see [README.md](README.md).

## Legal

Pull requests require agreement to [CLA.md](CLA.md). Contributions are owned
by MainMoney SARL.

## Clone

```bash
git clone git@github.com:MainMoney-Inc/mm_aggr_woocommerce.git
```

From the contrib hub, this tree is `contrib/plugins/woocommerce` with the PHP
SDK at `contrib/sdks/php`.

## Setup

```bash
composer install
npm install
npm run build
```

## Test

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Branches and commits

- `feature/<name>`, `bugfix/<name>`, `hotfix/<issue>`, `refactor/<description>`
- Conventional commits: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## Pull requests

- Include tests for behavior changes.
- Do not invent merchant API endpoints; use `/api/v1/schema/merchants/`.
- Do not commit secrets. Do not depend on the WordPress plugin repository.
- Local demos live under `examples/`. Downloaded WordPress core is gitignored.
