<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prova del consenso dato in registrazione: quando e su quale versione di informativa/condizioni. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->timestamp('privacy_accettata_at')->nullable()->after('approvato_at');
            $table->string('privacy_versione', 20)->nullable()->after('privacy_accettata_at');
        });
    }

    public function down(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['privacy_accettata_at', 'privacy_versione']);
        });
    }
};
