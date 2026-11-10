<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Richiesta del cliente: prezzi Shopify arrotondati per eccesso al ,90 (36,12 → 36,90).
 * Si imposta solo se non c'è già un valore (si cambia o si toglie da Configurazioni Sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_config')->insertOrIgnore([
            'key' => 'shopify_arrotondamento_centesimi',
            'value' => json_encode(90),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_config')->where('key', 'shopify_arrotondamento_centesimi')->delete();
    }
};
