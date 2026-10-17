# Changelog

## 0.1.8 (2026-10-17)

- Added checkout changes

## 0.1.7 (2026-10-17)

- Added some features

## 0.1.6 (2026-10-17)

- Added checkout caches and others
- feat: handle delayed payment confirmations with an on-hold order, thank-you page polling and a reconciler

## 0.1.5 (2026-10-16)

- ci: add Dependabot and CodeQL
- fix: send REST nonce for the checkout session; feat: Reports, Transactions, Wallets and Settings sub-menus

## Unreleased

- fix: a deposit that is still PENDING keeps the order in "Pending payment" (no more On hold); the webhook, thank-you poll, reconciler and "Refetch status" action now handle pending orders
- feat: the phone number lookup is debounced (600 ms after the last keystroke, from 7 digits) with a small spinner while the number is checked; the input keeps focus when the lookup finishes
- fix: the customer never sees "Merchant receives", the merchant fee or the net amount
- fix: a failed status poll (NetworkError, 5xx) no longer ends the checkout or redirects. The widget keeps polling with backoff and shows "connection lost, retrying"; only a failed/cancelled/expired payment shows Retry. The order is placed once the first status poll succeeded
- feat: the processing message tells the customer they can leave the page and will be notified
- feat: order action "Refetch status from MainMoney" for on-hold/pending MainMoney orders
- fix: the checkout no longer changes the cart currency. The order stays in the cart currency; when the provider accepts several currencies the customer picks the currency to pay in, with an automatic conversion quote. Without a quote the widget says conversion is not available and asks to select the cart currency
- fix: "Idempotency-Key was already used with a different request body". The key now follows the checkout session (a refreshed page or a retry starts a new session with a new reference), and a failed payment shows only the error and a Retry button
- fix: countries are restricted to those allowed for the merchant (also when a phone number matches a provider)
- feat: countries, providers, limits and checkout preferences are cached (24h; match-provider 10 min, never with account-holder lookups) and renewed when settings are saved or from the new "Clear cache" button
- feat: the receipt email is pre-filled from the billing or account email and can be changed
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
