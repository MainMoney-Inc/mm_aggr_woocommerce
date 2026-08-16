<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

interface CheckoutSessionStore
{
    public function save(CheckoutSession $session): void;

    public function find(string $token): ?CheckoutSession;
}
