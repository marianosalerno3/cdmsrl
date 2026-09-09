<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campi e tabelle necessari all'integrazione WinMino.
 * Vedi docs/winmino-integration.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colori', function (Blueprint $table) {
            $table->string('codice', 20)->nullable()->after('nome')->index(); // codice colore WinMino
        });

        Schema::table('taglie', function (Blueprint $table) {
            $table->string('codice', 6)->nullable()->after('nome')->index();   // codice taglia WinMino
        });

        Schema::table('prodotti', function (Blueprint $table) {
            $table->string('unita', 3)->nullable()->after('tipo');             // unità di misura riga ordine
        });

        Schema::table('clienti', function (Blueprint $table) {
            $table->string('criterio_sconto', 1)->nullable();                  // N|A|S|L
            $table->string('zona', 6)->nullable();
            $table->string('nazione_erp', 6)->nullable();
            $table->string('tipologia_fe', 2)->nullable();                     // PR|PA
            $table->string('tipologia_codice_fe', 1)->nullable();              // P (PEC) | C (SDI)
        });

        Schema::table('ordini_b2b', function (Blueprint $table) {
            $table->string('idesterno', 20)->nullable()->index();              // = numero, chiave idempotenza WinMino
            $table->json('erp_response')->nullable();                          // risposta grezza AddOrdineCliente
        });

        Schema::create('destinazioni', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codice', 6)->unique();                             // chiave upsert AddDestinazione (gestita da noi)
            $table->foreignUuid('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $table->string('nome', 50);
            $table->string('indirizzo', 80)->nullable();
            $table->string('localita', 40)->nullable();
            $table->string('provincia', 4)->nullable();
            $table->string('cap', 8)->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('cellulare', 15)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('codice_fiscale', 16)->nullable();
            $table->string('partita_iva', 15)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('sincronizzato_erp_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinazioni');

        Schema::table('ordini_b2b', function (Blueprint $table) {
            $table->dropColumn(['idesterno', 'erp_response']);
        });
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['criterio_sconto', 'zona', 'nazione_erp', 'tipologia_fe', 'tipologia_codice_fe']);
        });
        Schema::table('prodotti', function (Blueprint $table) {
            $table->dropColumn('unita');
        });
        Schema::table('taglie', function (Blueprint $table) {
            $table->dropColumn('codice');
        });
        Schema::table('colori', function (Blueprint $table) {
            $table->dropColumn('codice');
        });
    }
};
