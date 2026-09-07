<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordini_b2b', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero')->unique();                 // ORD-YYYYMMDD-XXXX

            $table->foreignUuid('agente_id')->nullable()->constrained('agenti')->nullOnDelete();
            $table->foreignUuid('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();

            // Snapshot cliente al momento dell'ordine
            $table->string('cliente_nome');
            $table->string('cliente_email')->nullable();
            $table->string('cliente_telefono')->nullable();
            $table->text('indirizzo_spedizione');

            $table->string('stato')->default('ricevuto');       // App\Enums\OrderStatus
            $table->string('metodo_pagamento')->nullable();     // App\Enums\PaymentMethod
            $table->string('listino_applicato')->default('standard');

            $table->decimal('subtotale', 12, 2)->default(0);
            $table->decimal('spese_spedizione', 10, 2)->default(0);
            $table->decimal('iva_perc', 5, 2)->default(22);
            $table->decimal('iva_importo', 12, 2)->default(0);
            $table->decimal('totale', 12, 2)->default(0);
            $table->unsignedInteger('totale_pezzi')->default(0);

            $table->text('note_agente')->nullable();
            $table->boolean('programmato')->default(false);
            $table->date('data_ordine');

            // Pagamento Stripe
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('stripe_payment_intent')->nullable();
            $table->timestamp('pagato_at')->nullable();

            // Email di conferma
            $table->timestamp('email_conferma_inviata_at')->nullable();

            // Integrazioni in uscita
            $table->timestamp('inviato_erp_at')->nullable();
            $table->string('shopify_order_id')->nullable()->index();
            $table->unsignedBigInteger('woocommerce_order_id')->nullable()->index();
            $table->json('wordpress_payload')->nullable();      // "Dati JSON WordPress"

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ordine_righe', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ordine_b2b_id')->constrained('ordini_b2b')->cascadeOnDelete();
            $table->foreignUuid('variante_prodotto_id')->nullable()->constrained('variante_prodotti')->nullOnDelete();

            // Snapshot riga
            $table->string('prodotto_codice');
            $table->string('prodotto_nome');
            $table->string('taglia')->nullable();
            $table->string('colore')->nullable();
            $table->string('sku')->nullable();

            $table->unsignedInteger('quantita');
            $table->decimal('prezzo_unitario', 10, 2);
            $table->decimal('totale_riga', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordine_righe');
        Schema::dropIfExists('ordini_b2b');
    }
};
