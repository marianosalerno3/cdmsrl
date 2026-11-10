<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.erp_username', null);
        $this->migrator->addEncrypted('integrations.erp_password', null);
    }

    public function down(): void
    {
        $this->migrator->delete('integrations.erp_username');
        $this->migrator->delete('integrations.erp_password');
    }
};
