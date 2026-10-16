# Changelog

## 0.1.5 (2026-10-16)

- ci: add Dependabot and CodeQL
- fix: send REST nonce for the checkout session; feat: Reports, Transactions, Wallets and Settings sub-menus

## Unreleased

- feat: slow provider confirmations. The order is placed **On hold** as soon as the deposit is PENDING; the thank-you page polls every second, shows a "request sent, waiting for the provider" message after 30s, and moves on when the result arrives
- feat: one idempotent order sync shared by the webhook, the thank-you page poll and a 5-minute reconciler, so late or lost webhooks no longer strand orders; webhooks that arrive before the order exists are kept and applied
- fix: send the REST nonce when creating the checkout session so logged-in customers no longer get "Your cart session could not be read"
- feat: MainMoney admin menu now has Reports, Transactions, Wallets and Settings sub-menus

## 0.1.4 (2026-10-16)

- feat: MainMoney square icon in Payments settings and checkout

## 0.1.3 (2026-10-16)

- fix: read the cart from the session in the REST session endpoint; add MainMoney admin menu and release script
- fix: verify payments before completing orders, mount widget in classic and Blocks checkout, add zip build

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
