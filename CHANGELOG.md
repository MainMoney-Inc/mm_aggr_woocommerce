# Changelog

## 0.1.2

- Orders are only placed after the deposit exists; unpaid/failed deposits are refused with a checkout notice.
- Order total and currency are verified against the deposit (checkout and webhook); mismatches stay on hold.
- Cart-bound checkout session endpoint (`POST /wc-mm-aggr/v1/session`); widget now survives classic checkout refreshes and mounts in Blocks checkout.
- Checkout is completed automatically when the widget reports SUCCESS.
- Webhooks are idempotent, ignore refund events and no longer echo error details.
- Refund failures return a safe message. Gateway hidden until Client ID and secret are set.
- `npm run zip` / `composer zip` build the installable plugin zip.

## 0.1.0

- Licensed under GPL-2.0-or-later.
- First release (Packagist `mainmoney/mm-aggr-woocommerce`, zip on GitHub Releases).
