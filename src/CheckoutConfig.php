<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

final class CheckoutConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function forSession(string $merchantBackendUrl, string $pollUrl, CheckoutSession $session, string $locale = 'en'): array
    {
        return [
            'merchantBackendUrl' => $merchantBackendUrl,
            'clientToken' => $session->token,
            'pollUrl' => $pollUrl,
            'pollHeaders' => [
                'Authorization' => 'Bearer '.$session->token,
            ],
            'locale' => $locale,
            'amount' => $session->amount,
            'lockAmount' => $session->lockAmount,
            'reference' => $session->reference,
        ];
    }
}
