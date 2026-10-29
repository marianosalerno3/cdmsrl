<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attributi / tassonomia prodotto:
 * stagioni, sopracategorie, categorie, generi, colori, taglie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stagioni', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codice')->unique();          // PE25, AI25, PE26...
            $table->string('nome')->nullable();
            $table->date('data_inizio')->nullable();
            $table->date('data_fine')->nullable();
            $table->boolean('attiva')->default(true);
            $table->boolean('programmata')->default(false); // "Programmato" nell'originale
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::create('sopracategorie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::create('categorie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique();            // ABITO, GIACCA, BORSA...
            $table->foreignUuid('sopracategoria_id')->nullable()->constrained('sopracategorie')->nullOnDelete();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::create('generi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique();            // Donna, Uomo, Unisex
            $table->timestamps();
        });

        Schema::create('colori', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique();            // dizionario ~500 valori del brand
            $table->string('hex', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('taglie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome')->unique();            // 38..50, XS..XXL, UN, UNICA
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taglie');
        Schema::dropIfExists('colori');
        Schema::dropIfExists('generi');
        Schema::dropIfExists('categorie');
        Schema::dropIfExists('sopracategorie');
        Schema::dropIfExists('stagioni');
    }
};
