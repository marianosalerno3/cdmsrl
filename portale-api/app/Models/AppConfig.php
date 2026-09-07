<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Store key-value per la configurazione di business esposta alla SPA.
 */
class AppConfig extends Model
{
    protected $table = 'app_config';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_config:{$key}", function () use ($key, $default) {
            return static::query()->find($key)?->value ?? $default;
        });
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_config:{$key}");
    }

    /** Payload completo per GET /api/app-config. */
    public static function payload(): array
    {
        return [
            'vat' => (float) static::get('vat', (float) env('BIZ_VAT_RATE', 22)),
            'spese_spedizione' => (float) static::get('spese_spedizione', (float) env('BIZ_SHIPPING_COST', 15)),
            'importo_minimo_ordine' => (float) static::get('importo_minimo_ordine', (float) env('BIZ_MIN_ORDER_AMOUNT', 0)),
            'giorni_evasione_programmati' => (int) static::get('giorni_evasione_programmati', (int) env('BIZ_SCHEDULED_ORDER_DAYS', 30)),
            'metodi_pagamento' => static::get('metodi_pagamento', ['stripe', 'bonifico', 'contrassegno']),
        ];
    }
}
