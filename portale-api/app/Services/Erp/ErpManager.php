<?php

namespace App\Services\Erp;

use App\Services\Erp\Contracts\ErpDriver;
use App\Services\Erp\Drivers\NullErpDriver;
use App\Services\Erp\Drivers\WinMinoDriver;
use App\Services\Erp\WinMino\DataSnapClient;
use App\Settings\IntegrationSettings;

/**
 * Punto di accesso all'ERP. Risolve il driver dai settings del pannello
 * (App\Settings\IntegrationSettings -> erp_driver).
 *
 *   app(ErpManager::class)->driver()->pushOrdine($ordine);
 */
class ErpManager
{
    private ?ErpDriver $resolved = null;

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function driver(): ErpDriver
    {
        return $this->resolved ??= $this->make($this->settings->erp_driver ?? 'null');
    }

    public function isConfigured(): bool
    {
        return $this->driver()->name() !== 'null';
    }

    private function make(string $name): ErpDriver
    {
        return match (strtolower($name)) {
            'winmino' => new WinMinoDriver($this->makeDataSnapClient()),
            default => new NullErpDriver,
        };
    }

    private function makeDataSnapClient(): DataSnapClient
    {
        return new DataSnapClient(
            baseUrl: rtrim((string) ($this->settings->erp_base_url ?: config('integrations.erp.base_url')), '/'),
            username: $this->settings->erp_username ?? null,
            password: $this->settings->erp_password ?? null,
            timeout: (int) config('winmino.http_timeout', 30),
            retries: (int) config('winmino.http_retries', 2),
            retrySleepMs: (int) config('winmino.http_retry_sleep_ms', 800),
        );
    }
}
