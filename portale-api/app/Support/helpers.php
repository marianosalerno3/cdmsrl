<?php

use App\Settings\IntegrationSettings;

if (! function_exists('settings_integration')) {
    /**
     * Legge una credenziale di integrazione dai settings del pannello
     * (spatie/laravel-settings), con fallback silenzioso a null se non
     * ancora migrati/configurati.
     */
    function settings_integration(string $key): mixed
    {
        try {
            $settings = app(IntegrationSettings::class);

            return $settings->{$key} ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
