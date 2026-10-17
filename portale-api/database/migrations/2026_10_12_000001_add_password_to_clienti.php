<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accesso al portale per i clienti B2B: dopo la registrazione e l'approvazione dell'admin
 * il cliente entra con email + password e ordina da solo (con il proprio listino).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('approvato_at')->nullable()->after('stato_richiesta');
        });

        // il backoffice deve sapere se l'ordine l'ha inserito il cliente da solo o un agente
        Schema::table('ordini_b2b', function (Blueprint $table) {
            $table->boolean('da_cliente')->default(false)->after('programmato');
        });
    }

    public function down(): void
    {
        Schema::table('ordini_b2b', function (Blueprint $table) {
            $table->dropColumn('da_cliente');
        });

        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['password', 'approvato_at']);
        });
    }
};
