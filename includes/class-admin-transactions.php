<?php

declare(strict_types=1);

use MainMoney\WooCommercePlugin\AdminApiHelper;


final class WC_Mm_Aggr_Admin_Transactions
{
    public static function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $operationType = isset($_GET['operation_type']) && is_string($_GET['operation_type'])
            ? sanitize_key($_GET['operation_type'])
            : 'deposit';
        $allowedOperations = ['deposit', 'payout', 'refund', 'remittance', 'settlement'];
        if (!in_array($operationType, $allowedOperations, true)) {
            $operationType = 'deposit';
        }

        $query = self::buildQuery();
        $client = WC_Mm_Aggr_Admin_Api::client();
        $error = null;
        $rows = [];
        $total = 0;

        if ($client === null) {
            $error = __('Configure merchant credentials under MainMoney → Settings.', 'woocommerce-mm-aggr');
        } else {
            $result = WC_Mm_Aggr_Admin_Api::call(fn () => $client->transactions->list($operationType, $query));
            if (!$result['ok']) {
                $error = $result['message'];
            } else {
                $list = AdminApiHelper::normalizeList($result['data']);
                $rows = $list['results'];
                $total = $list['count'];
            }
        }

        echo '<div class="wrap mm-aggr-admin">';
        echo '<h1>'.esc_html__('Transactions', 'woocommerce-mm-aggr').'</h1>';
        if ($error !== null) {
            echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
        }
        self::renderFilters($operationType, $query);
        echo '<p class="description">'.esc_html(sprintf(
            /* translators: %d: transaction count */
            __('Showing %d transactions.', 'woocommerce-mm-aggr'),
            $total,
        )).'</p>';
        echo '<table class="widefat striped mm-aggr-table"><thead><tr>';
        echo '<th>'.esc_html__('Reference', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Merchant ref', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Amount', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Provider', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Status', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Created', 'woocommerce-mm-aggr').'</th>';
        echo '</tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="6">'.esc_html__('No transactions found.', 'woocommerce-mm-aggr').'</td></tr>';
        } else {
            foreach ($rows as $row) {
                $amount = isset($row['amount']) ? (string) $row['amount'] : '';
                $currency = isset($row['currency']) ? (string) $row['currency'] : '';
                $created = isset($row['created_at']) ? (string) $row['created_at'] : '';
                echo '<tr>';
                echo '<td>'.esc_html((string) ($row['internal_reference'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['merchant_reference'] ?? '')).'</td>';
                echo '<td>'.esc_html(trim($amount.' '.$currency)).'</td>';
                echo '<td>'.esc_html((string) ($row['provider_code'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['status'] ?? '')).'</td>';
                echo '<td>'.esc_html($created).'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function buildQuery(): array
    {
        $query = [];
        if (isset($_GET['currency']) && is_string($_GET['currency']) && $_GET['currency'] !== '') {
            $query['currency'] = strtoupper(sanitize_text_field($_GET['currency']));
        }
        if (isset($_GET['provider_code']) && is_string($_GET['provider_code']) && $_GET['provider_code'] !== '') {
            $query['provider_code'] = sanitize_text_field($_GET['provider_code']);
        }
        if (isset($_GET['status']) && is_string($_GET['status']) && $_GET['status'] !== '') {
            $query['status'] = sanitize_text_field($_GET['status']);
        }
        if (isset($_GET['date_from']) && is_string($_GET['date_from']) && $_GET['date_from'] !== '') {
            $query['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        if (isset($_GET['date_to']) && is_string($_GET['date_to']) && $_GET['date_to'] !== '') {
            $query['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        if (isset($_GET['merchant_reference']) && is_string($_GET['merchant_reference']) && $_GET['merchant_reference'] !== '') {
            $query['merchant_reference'] = sanitize_text_field($_GET['merchant_reference']);
        }
        if (isset($_GET['amount_min']) && is_string($_GET['amount_min']) && $_GET['amount_min'] !== '') {
            $query['amount_min'] = sanitize_text_field($_GET['amount_min']);
        }
        if (isset($_GET['amount_max']) && is_string($_GET['amount_max']) && $_GET['amount_max'] !== '') {
            $query['amount_max'] = sanitize_text_field($_GET['amount_max']);
        }
        if (isset($_GET['ordering']) && is_string($_GET['ordering']) && $_GET['ordering'] !== '') {
            $query['ordering'] = sanitize_text_field($_GET['ordering']);
        }

        return $query;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private static function renderFilters(string $operationType, array $query): void
    {
        echo '<form method="get" class="mm-aggr-filters">';
        echo '<input type="hidden" name="page" value="mm-aggr-transactions" />';
        echo '<label>'.esc_html__('Type', 'woocommerce-mm-aggr');
        echo '<select name="operation_type">';
        foreach (['deposit', 'payout', 'refund', 'remittance', 'settlement'] as $type) {
            echo '<option value="'.esc_attr($type).'"'.selected($operationType, $type, false).'>'.esc_html(ucfirst($type)).'</option>';
        }
        echo '</select></label>';
        echo '<label>'.esc_html__('Currency', 'woocommerce-mm-aggr').'<input name="currency" type="text" value="'.esc_attr((string) ($query['currency'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('Provider', 'woocommerce-mm-aggr').'<input name="provider_code" type="text" value="'.esc_attr((string) ($query['provider_code'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('Status', 'woocommerce-mm-aggr').'<input name="status" type="text" value="'.esc_attr((string) ($query['status'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('From', 'woocommerce-mm-aggr').'<input name="date_from" type="date" value="'.esc_attr(self::dateOnly((string) ($query['date_from'] ?? ''))).'" /></label>';
        echo '<label>'.esc_html__('To', 'woocommerce-mm-aggr').'<input name="date_to" type="date" value="'.esc_attr(self::dateOnly((string) ($query['date_to'] ?? ''))).'" /></label>';
        echo '<label>'.esc_html__('Merchant ref', 'woocommerce-mm-aggr').'<input name="merchant_reference" type="text" value="'.esc_attr((string) ($query['merchant_reference'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('Min amount', 'woocommerce-mm-aggr').'<input name="amount_min" type="text" value="'.esc_attr((string) ($query['amount_min'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('Max amount', 'woocommerce-mm-aggr').'<input name="amount_max" type="text" value="'.esc_attr((string) ($query['amount_max'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('Order', 'woocommerce-mm-aggr');
        echo '<select name="ordering">';
        foreach (['-created_at', 'created_at', '-amount', 'amount', 'status', '-status'] as $ordering) {
            echo '<option value="'.esc_attr($ordering).'"'.selected((string) ($query['ordering'] ?? '-created_at'), $ordering, false).'>'.esc_html($ordering).'</option>';
        }
        echo '</select></label>';
        submit_button(__('Filter', 'woocommerce-mm-aggr'), 'secondary', '', false);
        echo '</form>';
    }

    private static function dateOnly(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) >= 10) {
            return substr($value, 0, 10);
        }

        return $value;
    }
}
