<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Richiesta del cliente: prezzo Shopify di Clara G. = listino base ingrosso × 2,8.
 * Si imposta solo se non è già stato scelto un valore (si cambia poi da Configurazioni Sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_config')->insertOrIgnore([
            'key' => 'shopify_moltiplicatori_linea',
            'value' => json_encode(['CLARA G' => 2.8]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_config')->where('key', 'shopify_moltiplicatori_linea')->delete();
    }
};
