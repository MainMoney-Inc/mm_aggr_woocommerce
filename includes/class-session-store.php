<?php

declare(strict_types=1);

use MainMoney\WooCommercePlugin\CheckoutSession;
use MainMoney\WooCommercePlugin\CheckoutSessionStore;

final class WC_Mm_Aggr_Session_Store implements CheckoutSessionStore
{
    private const PREFIX = 'wc_mm_aggr_sess_';

    public function save(CheckoutSession $session): void
    {
        $ttl = max(60, $session->expiresAt - time());
        set_transient(self::PREFIX.$session->token, [
            'token' => $session->token,
            'reference' => $session->reference,
            'amount' => $session->amount,
            'currency' => $session->currency,
            'lock_amount' => $session->lockAmount,
            'expires_at' => $session->expiresAt,
        ], $ttl);
        if (function_exists('WC') && WC()->session !== null) {
            WC()->session->set('mm_aggr_token', $session->token);
            WC()->session->set('mm_aggr_reference', $session->reference);
        }
    }

    public function find(string $token): ?CheckoutSession
    {
        $raw = get_transient(self::PREFIX.$token);
        if (!is_array($raw)) {
            return null;
        }
        $session = new CheckoutSession(
            token: isset($raw['token']) && is_string($raw['token']) ? $raw['token'] : $token,
            reference: isset($raw['reference']) && is_string($raw['reference']) ? $raw['reference'] : '',
            amount: isset($raw['amount']) && is_string($raw['amount']) ? $raw['amount'] : null,
            currency: isset($raw['currency']) && is_string($raw['currency']) ? $raw['currency'] : null,
            lockAmount: !empty($raw['lock_amount']),
            expiresAt: isset($raw['expires_at']) && is_int($raw['expires_at']) ? $raw['expires_at'] : 0,
        );
        if ($session->isExpired()) {
            delete_transient(self::PREFIX.$token);

            return null;
        }

        return $session;
    }
}
