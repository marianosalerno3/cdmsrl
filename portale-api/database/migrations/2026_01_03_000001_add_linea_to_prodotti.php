<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Linea/brand del prodotto (OLTRETEMPO, CLASSE DI VALENTINA, CLARA G) — da WinMino CODLINEA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prodotti', function (Blueprint $table) {
            $table->string('linea')->nullable()->index()->after('pacchetto');
        });
    }

    public function down(): void
    {
        Schema::table('prodotti', function (Blueprint $table) {
            $table->dropIndex(['linea']);
            $table->dropColumn('linea');
        });
    }
};
