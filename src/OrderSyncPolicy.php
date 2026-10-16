<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

/**
 * Decides what to do with a WooCommerce order given a verified deposit outcome.
 * Pure so the webhook, the thank-you page poll and the reconciler all behave identically.
 */
final class OrderSyncPolicy
{
    public const COMPLETE = 'complete';
    public const FAIL = 'fail';
    public const HOLD_MISMATCH = 'hold_mismatch';
    public const NONE = 'none';

    /** Statuses in which a late result may still change the order. */
    private const OPEN_STATUSES = ['pending', 'on-hold'];

    /**
     * @param string $orderStatus current WooCommerce status without the `wc-` prefix
     * @param string $outcome one of the PaymentVerifier constants
     */
    public static function decide(string $orderStatus, bool $isPaid, string $outcome): string
    {
        if ($isPaid || !in_array($orderStatus, self::OPEN_STATUSES, true)) {
            return self::NONE;
        }

        return match ($outcome) {
            PaymentVerifier::PAID => self::COMPLETE,
            PaymentVerifier::FAILED => self::FAIL,
            PaymentVerifier::MISMATCH => self::HOLD_MISMATCH,
            default => self::NONE,
        };
    }

    /** Public state returned to the thank-you page poller. */
    public static function publicState(string $orderStatus, bool $isPaid, string $outcome): string
    {
        if ($isPaid || in_array($orderStatus, ['processing', 'completed'], true)) {
            return 'paid';
        }
        if (in_array($orderStatus, ['failed', 'cancelled'], true) || $outcome === PaymentVerifier::FAILED) {
            return 'failed';
        }
        if ($outcome === PaymentVerifier::MISMATCH) {
            return 'mismatch';
        }

        return 'pending';
    }
}
