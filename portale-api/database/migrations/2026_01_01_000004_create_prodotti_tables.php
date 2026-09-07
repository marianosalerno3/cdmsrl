<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prodotti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codice')->unique();                 // B003/02, CINT032/02...
            $table->string('nome');
            $table->text('descrizione')->nullable();
            $table->string('composizione')->nullable();
            $table->string('tessuto')->nullable();
            $table->string('pacchetto')->nullable();            // "Pacchetto/Confezione"
            $table->string('tipo')->default('variabile');       // App\Enums\ProdottoTipo

            $table->foreignUuid('categoria_id')->nullable()->constrained('categorie')->nullOnDelete();
            $table->foreignUuid('stagione_id')->nullable()->constrained('stagioni')->nullOnDelete();
            $table->foreignUuid('genere_id')->nullable()->constrained('generi')->nullOnDelete();

            $table->decimal('prezzo_base', 10, 2)->nullable();  // fallback per prodotti "semplici"
            $table->boolean('attivo')->default(true);

            // mapping e-commerce
            $table->string('shopify_product_id')->nullable()->index();
            $table->unsignedBigInteger('woocommerce_product_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prodotto_immagini', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodotto_id')->constrained('prodotti')->cascadeOnDelete();
            $table->string('percorso');                         // path su disco "public" -> /storage/...
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::create('variante_prodotti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prodotto_id')->constrained('prodotti')->cascadeOnDelete();
            $table->foreignUuid('taglia_id')->nullable()->constrained('taglie')->nullOnDelete();
            $table->foreignUuid('colore_id')->nullable()->constrained('colori')->nullOnDelete();
            $table->string('sku')->unique();
            $table->decimal('prezzo', 10, 2)->default(0);       // prezzo base (listino "standard")
            $table->integer('quantita')->default(0);            // giacenza
            $table->string('barcode')->nullable();
            $table->string('shopify_variant_id')->nullable()->index();
            $table->unsignedBigInteger('woocommerce_variation_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['prodotto_id', 'taglia_id', 'colore_id'], 'variante_unica');
        });

        Schema::create('variante_immagini', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variante_prodotto_id')->constrained('variante_prodotti')->cascadeOnDelete();
            $table->string('percorso');
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variante_immagini');
        Schema::dropIfExists('variante_prodotti');
        Schema::dropIfExists('prodotto_immagini');
        Schema::dropIfExists('prodotti');
    }
};
