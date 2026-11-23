<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // consenso alle comunicazioni commerciali (promemoria carrello, novità): data di consenso e di eventuale revoca
        Schema::table('clienti', function (Blueprint $table) {
            $table->timestamp('consenso_comunicazioni_at')->nullable()->after('privacy_versione');
            $table->timestamp('consenso_comunicazioni_revocato_at')->nullable()->after('consenso_comunicazioni_at');
        });

        // carrello salvato sul server (uno per cliente), per poterlo riprendere e per il promemoria
        Schema::create('carrelli', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->unique()->constrained('clienti')->cascadeOnDelete();
            $table->foreignUuid('agente_id')->nullable()->constrained('agenti')->nullOnDelete(); // chi l'ha caricato, se un agente
            $table->json('righe');
            $table->decimal('totale', 12, 2)->default(0);
            $table->timestamp('aggiornato_at')->nullable()->index();
            $table->timestamp('promemoria_inviato_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrelli');
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['consenso_comunicazioni_at', 'consenso_comunicazioni_revocato_at']);
        });
    }
};
