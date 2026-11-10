<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richieste di sostituzione (resi/cambi) collegate a un ordine originale.
 * Nell'originale: rotta SPA /sostituzioni, endpoint POST /returns, GET /agent/returns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sostituzioni', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero')->unique();                 // SOST-YYYYMMDD-XXXX
            $table->foreignUuid('ordine_originale_id')->constrained('ordini_b2b')->cascadeOnDelete();
            $table->foreignUuid('agente_id')->nullable()->constrained('agenti')->nullOnDelete();
            $table->foreignUuid('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();

            $table->string('stato')->default('richiesta');      // richiesta|approvata|rifiutata|evasa
            $table->text('motivazione')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sostituzione_righe', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sostituzione_id')->constrained('sostituzioni')->cascadeOnDelete();

            // Variante resa
            $table->foreignUuid('variante_resa_id')->nullable()->constrained('variante_prodotti')->nullOnDelete();
            // Variante richiesta in cambio
            $table->foreignUuid('variante_richiesta_id')->nullable()->constrained('variante_prodotti')->nullOnDelete();

            $table->unsignedInteger('quantita');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sostituzione_righe');
        Schema::dropIfExists('sostituzioni');
    }
};
