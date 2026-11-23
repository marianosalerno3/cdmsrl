<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacchetti del catalogo: raggruppamenti creati da CDM nel pannello (non quelli di WinMino, che sono
 * etichette di produzione/provenienza: 255 prodotti su 277 stanno in un unico "pacchetto" ERP).
 * Servono a filtrare e ordinare il catalogo; l'ordine tra pacchetti lo decide l'azienda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacchetti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::table('prodotti', function (Blueprint $table) {
            // la colonna testuale `pacchetto` resta quella importata da ERP; questa è l'assegnazione fatta nel portale
            $table->foreignUuid('pacchetto_id')->nullable()->after('pacchetto')->constrained('pacchetti')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prodotti', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pacchetto_id');
        });

        Schema::dropIfExists('pacchetti');
    }
};
