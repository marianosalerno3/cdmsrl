<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Credenziali integrazioni, editabili dal pannello (pagina "Configurazioni Sistema").
 * I valori sensibili vanno castati come "encrypted" (vedi config/settings.php).
 */
class IntegrationSettings extends Settings
{
    // WordPress / WooCommerce
    public ?string $woocommerce_url = null;
    public ?string $woocommerce_consumer_key = null;
    public ?string $woocommerce_consumer_secret = null;

    // Shopify
    public ?string $shopify_shop_domain = null;
    public ?string $shopify_access_token = null;

    // Stripe
    public ?string $stripe_key = null;
    public ?string $stripe_secret = null;
    public ?string $stripe_webhook_secret = null;

    // ERP (WinMino o equivalente)
    public ?string $erp_driver = 'null';
    public ?string $erp_base_url = null;
    public ?string $erp_api_key = null;

    // Generali
    public bool $sync_giacenze_automatica = false;

    public static function group(): string
    {
        return 'integrations';
    }

    public static function encrypted(): array
    {
        return [
            'woocommerce_consumer_secret',
            'shopify_access_token',
            'stripe_secret',
            'stripe_webhook_secret',
            'erp_api_key',
        ];
    }
}
