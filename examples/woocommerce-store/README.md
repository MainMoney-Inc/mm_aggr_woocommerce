# WooCommerce example store

Standalone WordPress + WooCommerce site with the MainMoney payment gateway.

Uses **native local MySQL/MariaDB** (set `DB_*` in `.env`). WooCommerce is not
reliable on SQLite.

Default port: **8081**.

## Requirements

- PHP 8.2+
- Composer
- [WP-CLI](https://wp-cli.org/)
- MySQL or MariaDB already running
- yarn (to build the plugin checkout bundle once, if needed)

## Setup

```bash
cp .env.example .env
# set MM_* credentials and DB_*
./scripts/bootstrap.sh
./scripts/seed.sh
./scripts/serve.sh
```

WordPress core is downloaded into `wordpress/` (gitignored).

Open http://127.0.0.1:8081/shop , add a demo product, checkout with MainMoney.
WP Admin: `admin` / `admin` (change in `.env`).

Webhook URL (after a tunnel): `https://your-host.example/wp-json/wc-mm-aggr/v1/webhooks`
