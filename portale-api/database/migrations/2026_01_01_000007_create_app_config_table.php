<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurazione di business esposta alla SPA da GET /api/app-config.
 * Key-value semplice; i valori tipici (dall'originale):
 *   importo_minimo_ordine, spese_spedizione, vat, giorni_evasione_programmati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_config', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_config');
    }
};
