<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MainMoney WooCommerce payment gateway stub.
 */
class WC_Gateway_Mm_Aggr extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'mm_aggr';
        $this->method_title = 'MainMoney';
        $this->method_description = 'MainMoney aggregator payments';
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();
    }
}
