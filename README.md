# MainMoney for WooCommerce

[![Packagist](https://img.shields.io/packagist/v/mainmoney/mm-aggr-woocommerce)](https://packagist.org/packages/mainmoney/mm-aggr-woocommerce) [![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

WooCommerce payment gateway for the MainMoney aggregator. Customers pay with the
MainMoney checkout widget (mobile money and the other methods enabled for your
merchant application). The order is completed and the cart emptied only after the
aggregator confirms the payment.

It uses the official [PHP SDK](https://github.com/MainMoney-Inc/mm_aggr_php_sdk) on the
server and the [JS/TS frontend SDK](https://github.com/MainMoney-Inc/mm_aggr_js_sdk) in
the browser. Your API secret never reaches the browser.

## Requirements

- WordPress 6.7+ and WooCommerce (latest stable recommended), classic or Blocks checkout
- PHP 8.2+
- A merchant application on MM Aggregator (Client ID, API secret, webhook secret)
- A public **HTTPS** site (the aggregator must be able to reach your webhook URL)

## 1. Get the plugin zip

Download `woocommerce-mm-aggr-<version>.zip` from the
[GitHub releases](https://github.com/MainMoney-Inc/mm_aggr_woocommerce/releases).
Dependencies are bundled; nothing else to install.

To build the zip yourself from a checkout (needs PHP 8.2+, Composer, Node 20+, `rsync`, `zip`):

```bash
composer install
npm install
npm run zip          # next version + tests + build/woocommerce-mm-aggr-<version>.zip + git tag
npm run zip:dry      # build a zip from the current tree without bumping or tagging
```

## 2. Install into an existing store

Pick one:

- **WordPress admin:** Plugins → Add New → Upload Plugin → choose the zip → Install → Activate.
- **SFTP / SSH:** unzip into `wp-content/plugins/` so you get
  `wp-content/plugins/woocommerce-mm-aggr/`, then activate it in Plugins.
- **WP-CLI:** `wp plugin install ./woocommerce-mm-aggr-<version>.zip --activate`
- **Composer-managed site:** `composer require mainmoney/mm-aggr-woocommerce`, then activate.

WooCommerce must be active first. If you see "requires Composer dependencies", you
uploaded a source checkout instead of the release zip.

## 3. Configure

Open the **MainMoney** entry in the WordPress admin sidebar (or WooCommerce → Settings → Payments → MainMoney). The sidebar page also has a webhook URL, status checks and a **Test connection** button:

| Field | Value |
| --- | --- |
| Enable | on |
| Title | name shown at checkout |
| Client ID / API secret | from your merchant application |
| Test mode | on = `testaggregator.mainmoney.net` (no real money); **off for real payments** |
| Webhook secret | the secret of your merchant webhook |
| Base URI override | leave empty unless MainMoney support gives you one |

The gateway stays hidden at checkout until Client ID and API secret are set.

Then, in the aggregator admin, set your merchant **webhook URL** to:

```
https://your-site.example/wp-json/wc-mm-aggr/v1/webhooks
```

If your site uses plain permalinks the URL is
`https://your-site.example/?rest_route=/wc-mm-aggr/v1/webhooks`.

## How a payment completes

1. The customer picks MainMoney at checkout. The widget gets a session bound to the
   **server-side cart total and currency** (the amount cannot be edited in the browser).
2. The customer chooses a provider, enters their number and confirms. The widget
   creates the deposit and waits for the result.
3. When the deposit is `SUCCESS` the widget places the order automatically.
4. On order placement the plugin asks the aggregator for the deposit by reference and
   checks that the amount and currency match the order:

| Aggregator result | Order | Cart | Customer sees |
| --- | --- | --- | --- |
| `SUCCESS`, amount/currency match | `payment_complete()` → *Processing* (or *Completed* for virtual/downloadable) | emptied | order received page |
| `PENDING` (customer still approving on the phone) | *On hold* | emptied | order received page; the webhook completes it later |
| `SUCCESS` but amount/currency differ | *On hold* with a note for you to review | emptied | order received page |
| `FAILED`, `CANCELLED`, `EXPIRED`, or no deposit yet | order not placed | **kept** | error notice, try again |

The signed webhook (`X-Webhook-Signature`) later moves *On hold* orders to
*Processing* on `SUCCESS`, or *Failed* on a failed/expired deposit. It is idempotent
and ignores already-paid orders.

Refunds: open the order → Refund → the amount is sent to MainMoney (the order must
have been paid through this gateway).

## Go-live checklist

- [ ] Test mode **off**, live Client ID / secret / webhook secret saved
- [ ] Site served over HTTPS; webhook URL set in the aggregator
- [ ] Webhook reachable from the internet (no firewall, basic-auth or maintenance mode on `/wp-json/wc-mm-aggr/`)
- [ ] Caching plugins/CDN do **not** cache the checkout page or `/wp-json/wc-mm-aggr/*`
- [ ] One small real payment: order reaches *Processing*, cart empty
- [ ] One small refund works

## Troubleshooting

- **MainMoney missing at checkout:** plugin inactive, gateway disabled, or Client ID/secret empty.
- **Widget blank or "MainMoney is unavailable":** open the browser console/network tab; `POST /wp-json/wc-mm-aggr/v1/session` must return 200. A 503 means credentials are not saved; a 409 means the cart session was not readable (reload; check that caches/CDN do not strip cookies on `/wp-json/`); a security/cache plugin may be blocking REST.
- **Order stays *On hold*:** the payment is still pending or the webhook is not arriving. Check the webhook URL and secret; failures are logged under WooCommerce → Status → Logs (source `mm-aggr`).
- **Webhook 401:** the webhook secret in WooCommerce differs from the one in the aggregator.
- **"Payment was not completed" at checkout:** the customer pressed Place order before approving; they should finish the payment in the widget first.

## Examples

A local WooCommerce store with this gateway is in [examples/woocommerce-store](examples/woocommerce-store).

## License

Copyright (c) 2026 MainMoney SARL. Licensed under the GNU General Public License, version 2 or (at your option) any later version (GPL-2.0-or-later). See [LICENSE](LICENSE).

Want to contribute? See [CONTRIBUTING.md](CONTRIBUTING.md).
