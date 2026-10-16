<?php

declare(strict_types=1);


final class WC_Mm_Aggr_Admin_Reports
{
    public static function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $query = self::buildQuery();
        $client = WC_Mm_Aggr_Admin_Api::client();
        $error = null;
        $summary = ['totals_by_provider' => [], 'top_transactions' => []];
        $charts = ['volume_by_day' => []];

        if ($client === null) {
            $error = __('Configure merchant credentials under MainMoney → Settings.', 'woocommerce-mm-aggr');
        } else {
            $summaryResult = WC_Mm_Aggr_Admin_Api::call(fn () => $client->reports->summary($query));
            if (!$summaryResult['ok']) {
                $error = $summaryResult['message'];
            } else {
                $summary = $summaryResult['data'];
            }

            $chartsResult = WC_Mm_Aggr_Admin_Api::call(fn () => $client->reports->charts($query));
            if (!$chartsResult['ok']) {
                $error = $chartsResult['message'];
            } else {
                $charts = $chartsResult['data'];
            }
        }

        $chartPayload = isset($charts['volume_by_day']) && is_array($charts['volume_by_day'])
            ? $charts['volume_by_day']
            : [];

        echo '<div class="wrap mm-aggr-admin">';
        echo '<h1>'.esc_html__('Reports', 'woocommerce-mm-aggr').'</h1>';
        if ($error !== null) {
            echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
        }
        self::renderFilters($query);

        $totals = isset($summary['totals_by_provider']) && is_array($summary['totals_by_provider'])
            ? $summary['totals_by_provider']
            : [];
        echo '<h2>'.esc_html__('Totals by provider', 'woocommerce-mm-aggr').'</h2>';
        echo '<table class="widefat striped mm-aggr-table"><thead><tr>';
        echo '<th>'.esc_html__('Provider', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Currency', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Count', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Total', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Success', 'woocommerce-mm-aggr').'</th>';
        echo '</tr></thead><tbody>';
        if ($totals === []) {
            echo '<tr><td colspan="5">'.esc_html__('No data for the selected filters.', 'woocommerce-mm-aggr').'</td></tr>';
        } else {
            foreach ($totals as $row) {
                echo '<tr>';
                echo '<td>'.esc_html((string) ($row['provider_code'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['currency'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['count'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['total_amount'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['success_count'] ?? '')).'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        $top = isset($summary['top_transactions']) && is_array($summary['top_transactions'])
            ? $summary['top_transactions']
            : [];
        echo '<h2>'.esc_html__('Top transactions by amount', 'woocommerce-mm-aggr').'</h2>';
        echo '<table class="widefat striped mm-aggr-table"><thead><tr>';
        echo '<th>'.esc_html__('Type', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Amount', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Provider', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Merchant ref', 'woocommerce-mm-aggr').'</th>';
        echo '<th>'.esc_html__('Created', 'woocommerce-mm-aggr').'</th>';
        echo '</tr></thead><tbody>';
        if ($top === []) {
            echo '<tr><td colspan="5">'.esc_html__('No transactions for the selected filters.', 'woocommerce-mm-aggr').'</td></tr>';
        } else {
            foreach ($top as $row) {
                $amount = (string) ($row['amount'] ?? '');
                $currency = (string) ($row['currency'] ?? '');
                echo '<tr>';
                echo '<td>'.esc_html((string) ($row['operation_type'] ?? '')).'</td>';
                echo '<td>'.esc_html(trim($amount.' '.$currency)).'</td>';
                echo '<td>'.esc_html((string) ($row['provider_code'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['merchant_reference'] ?? '')).'</td>';
                echo '<td>'.esc_html((string) ($row['created_at'] ?? '')).'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h2>'.esc_html__('Volume by day', 'woocommerce-mm-aggr').'</h2>';
        echo '<canvas id="mm-aggr-volume-chart" width="800" height="320"></canvas>';
        echo '<script type="application/json" id="mm-aggr-chart-data">'.esc_html(wp_json_encode($chartPayload)).'</script>';
        echo '</div>';
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function buildQuery(): array
    {
        $query = [];
        if (isset($_GET['date_from']) && is_string($_GET['date_from']) && $_GET['date_from'] !== '') {
            $query['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        if (isset($_GET['date_to']) && is_string($_GET['date_to']) && $_GET['date_to'] !== '') {
            $query['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        if (isset($_GET['operation_type']) && is_string($_GET['operation_type']) && $_GET['operation_type'] !== '') {
            $query['operation_type'] = sanitize_key($_GET['operation_type']);
        }
        if (isset($_GET['currency']) && is_string($_GET['currency']) && $_GET['currency'] !== '') {
            $query['currency'] = strtoupper(sanitize_text_field($_GET['currency']));
        }
        if (isset($_GET['provider_code']) && is_string($_GET['provider_code']) && $_GET['provider_code'] !== '') {
            $query['provider_code'] = sanitize_text_field($_GET['provider_code']);
        }

        return $query;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private static function renderFilters(array $query): void
    {
        echo '<form method="get" class="mm-aggr-filters">';
        echo '<input type="hidden" name="page" value="mm-aggr" />';
        echo '<label>'.esc_html__('From', 'woocommerce-mm-aggr').'<input name="date_from" type="date" value="'.esc_attr(self::dateOnly((string) ($query['date_from'] ?? ''))).'" /></label>';
        echo '<label>'.esc_html__('To', 'woocommerce-mm-aggr').'<input name="date_to" type="date" value="'.esc_attr(self::dateOnly((string) ($query['date_to'] ?? ''))).'" /></label>';
        echo '<label>'.esc_html__('Type', 'woocommerce-mm-aggr');
        echo '<select name="operation_type"><option value="">'.esc_html__('All', 'woocommerce-mm-aggr').'</option>';
        foreach (['deposit', 'payout', 'refund', 'remittance', 'settlement'] as $type) {
            echo '<option value="'.esc_attr($type).'"'.selected((string) ($query['operation_type'] ?? ''), $type, false).'>'.esc_html(ucfirst($type)).'</option>';
        }
        echo '</select></label>';
        echo '<label>'.esc_html__('Currency', 'woocommerce-mm-aggr').'<input name="currency" type="text" value="'.esc_attr((string) ($query['currency'] ?? '')).'" /></label>';
        echo '<label>'.esc_html__('Provider', 'woocommerce-mm-aggr').'<input name="provider_code" type="text" value="'.esc_attr((string) ($query['provider_code'] ?? '')).'" /></label>';
        submit_button(__('Apply', 'woocommerce-mm-aggr'), 'secondary', '', false);
        echo '</form>';
    }

    private static function dateOnly(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return strlen($value) >= 10 ? substr($value, 0, 10) : $value;
    }
}
