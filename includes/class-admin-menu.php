<?php

declare(strict_types=1);

use MainMoney\Aggregator\Exception\AggregatorException;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\Settings;
use MainMoney\WooCommercePlugin\SettingsSanitizer;

final class WC_Mm_Aggr_Admin_Menu
{
    private const SLUG = 'mm-aggr';
    private const OPTION = 'woocommerce_mm_aggr_settings';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_mm_aggr_save', [self::class, 'save']);
        add_action('admin_post_mm_aggr_test', [self::class, 'testConnection']);
        add_filter('plugin_action_links_'.plugin_basename(WC_MM_AGGR_FILE), [self::class, 'actionLinks']);
    }

    public static function url(): string
    {
        return admin_url('admin.php?page='.self::SLUG);
    }

    public static function menu(): void
    {
        add_menu_page(
            __('MainMoney', 'woocommerce-mm-aggr'),
            __('MainMoney', 'woocommerce-mm-aggr'),
            'manage_woocommerce',
            self::SLUG,
            [self::class, 'render'],
            'dashicons-money-alt',
            56,
        );
    }

    /**
     * @param list<string> $links
     *
     * @return list<string>
     */
    public static function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="'.esc_url(self::url()).'">'.esc_html__('Settings', 'woocommerce-mm-aggr').'</a>');

        return $links;
    }

    public static function save(): void
    {
        self::guard('mm_aggr_save');
        $existing = get_option(self::OPTION, []);
        $input = wp_unslash($_POST['mm'] ?? []);
        $clean = SettingsSanitizer::sanitize(is_array($input) ? $input : [], is_array($existing) ? $existing : []);
        update_option(self::OPTION, array_merge(is_array($existing) ? $existing : [], $clean));
        self::back('saved');
    }

    public static function testConnection(): void
    {
        self::guard('mm_aggr_test');
        $options = get_option(self::OPTION, []);
        $options = is_array($options) ? $options : [];
        $settings = new Settings(
            clientId: (string) ($options['client_id'] ?? ''),
            secret: (string) ($options['secret'] ?? ''),
            test: ($options['test'] ?? 'no') === 'yes',
            baseUri: ($options['base_uri'] ?? '') !== '' ? (string) $options['base_uri'] : null,
            webhookSecret: (string) ($options['webhook_secret'] ?? ''),
        );
        if (!$settings->isConfigured()) {
            self::back('test_missing');
        }
        try {
            ClientFactory::fromSettings($settings)->checkoutPreferences->get();
        } catch (AggregatorException $exception) {
            wc_get_logger()->warning('MainMoney connection test failed: '.$exception->getMessage(), ['source' => 'mm-aggr']);
            self::back('test_failed');
        }
        self::back('test_ok');
    }

    public static function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $o = get_option(self::OPTION, []);
        $o = is_array($o) ? $o : [];
        $value = static fn (string $key, string $default = ''): string => isset($o[$key]) && is_string($o[$key]) ? $o[$key] : $default;
        $notice = isset($_GET['mm_notice']) ? sanitize_key((string) wp_unslash($_GET['mm_notice'])) : '';
        $messages = [
            'saved' => [__('Settings saved.', 'woocommerce-mm-aggr'), 'success'],
            'test_ok' => [__('Connection successful: the credentials are accepted.', 'woocommerce-mm-aggr'), 'success'],
            'test_failed' => [__('Connection failed. Check Client ID, API secret and test mode (details in WooCommerce > Status > Logs, source mm-aggr).', 'woocommerce-mm-aggr'), 'error'],
            'test_missing' => [__('Save the Client ID and API secret first.', 'woocommerce-mm-aggr'), 'warning'],
        ];
        echo '<div class="wrap"><h1>'.esc_html__('MainMoney', 'woocommerce-mm-aggr').'</h1>';
        if (isset($messages[$notice])) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($messages[$notice][1]), esc_html($messages[$notice][0]));
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="mm_aggr_save">';
        wp_nonce_field('mm_aggr_save');
        echo '<table class="form-table" role="presentation">';
        self::row(__('Enable', 'woocommerce-mm-aggr'), '<label><input type="checkbox" name="mm[enabled]" value="1" '.checked($value('enabled', 'yes'), 'yes', false).'> '.esc_html__('Enable MainMoney at checkout', 'woocommerce-mm-aggr').'</label>');
        self::row(__('Title', 'woocommerce-mm-aggr'), '<input class="regular-text" name="mm[title]" value="'.esc_attr($value('title', 'MainMoney')).'">');
        self::row(__('Client ID', 'woocommerce-mm-aggr'), '<input class="regular-text" name="mm[client_id]" value="'.esc_attr($value('client_id')).'" autocomplete="off">');
        self::row(__('API secret', 'woocommerce-mm-aggr'), '<input class="regular-text" type="password" name="mm[secret]" value="" autocomplete="new-password" placeholder="'.esc_attr($value('secret') !== '' ? '••••••••' : '').'"><p class="description">'.esc_html__('Leave blank to keep the saved secret.', 'woocommerce-mm-aggr').'</p>');
        self::row(__('Webhook secret', 'woocommerce-mm-aggr'), '<input class="regular-text" type="password" name="mm[webhook_secret]" value="" autocomplete="new-password" placeholder="'.esc_attr($value('webhook_secret') !== '' ? '••••••••' : '').'"><p class="description">'.esc_html__('Leave blank to keep the saved secret.', 'woocommerce-mm-aggr').'</p>');
        self::row(__('Test mode', 'woocommerce-mm-aggr'), '<label><input type="checkbox" name="mm[test]" value="1" '.checked($value('test', 'no'), 'yes', false).'> '.esc_html__('Use testaggregator.mainmoney.net (no real money). Turn off for real payments.', 'woocommerce-mm-aggr').'</label>');
        self::row(__('Base URI override', 'woocommerce-mm-aggr'), '<input class="regular-text" name="mm[base_uri]" value="'.esc_attr($value('base_uri')).'"><p class="description">'.esc_html__('Optional. Leave empty to use the SDK hosts.', 'woocommerce-mm-aggr').'</p>');
        echo '</table>';
        submit_button(__('Save settings', 'woocommerce-mm-aggr'));
        echo '</form>';

        echo '<h2>'.esc_html__('Status', 'woocommerce-mm-aggr').'</h2><table class="widefat striped" style="max-width:800px"><tbody>';
        $webhook = rest_url('wc-mm-aggr/v1/webhooks');
        $checks = [
            __('Webhook URL (set it in the aggregator)', 'woocommerce-mm-aggr') => '<code>'.esc_html($webhook).'</code>',
            __('HTTPS', 'woocommerce-mm-aggr') => is_ssl() ? '✔' : '✖ '.esc_html__('Use HTTPS for real payments', 'woocommerce-mm-aggr'),
            __('Credentials saved', 'woocommerce-mm-aggr') => ($value('client_id') !== '' && $value('secret') !== '') ? '✔' : '✖',
            __('Webhook secret saved', 'woocommerce-mm-aggr') => $value('webhook_secret') !== '' ? '✔' : '✖',
            __('Mode', 'woocommerce-mm-aggr') => $value('test', 'no') === 'yes' ? esc_html__('Test', 'woocommerce-mm-aggr') : esc_html__('Live', 'woocommerce-mm-aggr'),
            __('Plugin / PHP / WooCommerce', 'woocommerce-mm-aggr') => esc_html(WC_MM_AGGR_VERSION.' / '.PHP_VERSION.' / '.(defined('WC_VERSION') ? WC_VERSION : '?')),
        ];
        foreach ($checks as $label => $html) {
            echo '<tr><th style="width:40%">'.esc_html($label).'</th><td>'.$html.'</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
        }
        echo '</tbody></table>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:1em">';
        echo '<input type="hidden" name="action" value="mm_aggr_test">';
        wp_nonce_field('mm_aggr_test');
        submit_button(__('Test connection', 'woocommerce-mm-aggr'), 'secondary', 'submit', false);
        echo ' <a class="button" href="'.esc_url(admin_url('admin.php?page=wc-status&tab=logs')).'">'.esc_html__('View logs', 'woocommerce-mm-aggr').'</a>';
        echo '</form></div>';
    }

    private static function row(string $label, string $fieldHtml): void
    {
        echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.$fieldHtml.'</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
    }

    private static function guard(string $action): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'woocommerce-mm-aggr'), 403);
        }
        check_admin_referer($action);
    }

    private static function back(string $notice): never
    {
        wp_safe_redirect(add_query_arg('mm_notice', $notice, self::url()));
        exit;
    }
}
