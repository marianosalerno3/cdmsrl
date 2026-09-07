<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.woocommerce_url', null);
        $this->migrator->add('integrations.woocommerce_consumer_key', null);
        $this->migrator->addEncrypted('integrations.woocommerce_consumer_secret', null);

        $this->migrator->add('integrations.shopify_shop_domain', null);
        $this->migrator->addEncrypted('integrations.shopify_access_token', null);

        $this->migrator->add('integrations.stripe_key', null);
        $this->migrator->addEncrypted('integrations.stripe_secret', null);
        $this->migrator->addEncrypted('integrations.stripe_webhook_secret', null);

        $this->migrator->add('integrations.erp_driver', 'null');
        $this->migrator->add('integrations.erp_base_url', null);
        $this->migrator->addEncrypted('integrations.erp_api_key', null);

        $this->migrator->add('integrations.sync_giacenze_automatica', false);
    }
};
