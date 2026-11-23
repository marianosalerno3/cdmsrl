<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clienti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agente_id')->nullable()->constrained('agenti')->nullOnDelete();

            $table->string('codice_cliente_erp')->nullable()->index();   // "codice cliente winmino"
            $table->string('tipo')->default('b2b');                      // App\Enums\ClienteTipo

            $table->string('ragione_sociale')->nullable();
            $table->string('nome')->nullable();
            $table->string('cognome')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('telefono')->nullable();

            $table->string('stato_richiesta')->default('in_attesa');     // App\Enums\RichiestaStato
            $table->string('partita_iva')->nullable();
            $table->string('codice_fiscale')->nullable();
            $table->string('codice_sdi')->nullable();
            $table->string('pec')->nullable();

            $table->string('tipo_listino')->nullable();                  // override del listino agente (null = usa quello dell'agente)
            $table->boolean('attivo')->default(true);
            $table->boolean('contrassegno_abilitato')->default(false);   // "Contrassegno" flag

            // Indirizzo sede
            $table->string('indirizzo')->nullable();
            $table->string('cap', 16)->nullable();
            $table->string('citta')->nullable();
            $table->string('provincia', 4)->nullable();
            $table->string('nazione', 2)->default('IT');

            // Documenti caricati in fase di registrazione B2B
            $table->json('documenti')->nullable();

            $table->timestamp('sincronizzato_erp_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clienti');
    }
};
