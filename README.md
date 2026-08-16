# MainMoney for WooCommerce

WooCommerce payment gateway for the MainMoney aggregator. Uses the official
[PHP SDK](https://github.com/MainMoney-Inc/mm_aggr_php_sdk) on the server and the
[JS/TS frontend SDK](https://github.com/MainMoney-Inc/mm_aggr_js_sdk) on checkout.

This plugin does not require the separate WordPress plugin.

## Requirements

- WordPress 6.7 or later, WooCommerce (latest stable recommended)
- PHP 8.2 or later
- Composer
- A merchant application on MM Aggregator

## Install

1. Install WooCommerce.
2. Copy this plugin into `wp-content/plugins/` and run `composer install`.
   Until Packagist lists the PHP SDK, Composer loads it from
   `../../sdks/php` (the contrib hub layout, or clone
   [mm_aggr_php_sdk](https://github.com/MainMoney-Inc/mm_aggr_php_sdk) there).
3. Activate **MainMoney for WooCommerce**.
4. WooCommerce → Settings → Payments → MainMoney: Client ID, API secret,
   Test mode, webhook secret. Leave Base URI empty unless you override the SDK hosts.
5. In the aggregator admin, set the merchant webhook URL to
   `https://your-site.example/wp-json/wc-mm-aggr/v1/webhooks`.

Checkout (classic and Blocks) mounts the official wizard with the order total
locked. Merchant API keys never leave the server.

## License

Copyright (c) 2026 MainMoney SARL. Licensed under the PolyForm Noncommercial
License 1.0.0. Commercial use requires permission from MainMoney SARL.
See [LICENSE](LICENSE).

Want to contribute? See [CONTRIBUTING.md](CONTRIBUTING.md).
