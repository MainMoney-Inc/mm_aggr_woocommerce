<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

final class InMemoryCheckoutSessionStore implements CheckoutSessionStore
{
    /**
     * @var array<string, CheckoutSession>
     */
    private array $sessions = [];

    public function save(CheckoutSession $session): void
    {
        $this->sessions[$session->token] = $session;
    }

    public function find(string $token): ?CheckoutSession
    {
        $session = $this->sessions[$token] ?? null;
        if ($session === null || $session->isExpired()) {
            return null;
        }

        return $session;
    }
}
