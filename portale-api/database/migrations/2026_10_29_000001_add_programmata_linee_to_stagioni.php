<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Programmato" si decide per stagione e, dove serve, per linea: `programmata` resta il valore
 * della stagione, `programmata_linee` elenca le eccezioni [{linea, programmata}].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stagioni', function (Blueprint $table) {
            $table->json('programmata_linee')->nullable()->after('programmata');
        });
    }

    public function down(): void
    {
        Schema::table('stagioni', function (Blueprint $table) {
            $table->dropColumn('programmata_linee');
        });
    }
};
