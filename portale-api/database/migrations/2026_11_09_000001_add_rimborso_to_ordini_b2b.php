<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rimborsi dei pagamenti con carta: importo restituito e quando Stripe l'ha comunicato. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordini_b2b', function (Blueprint $table) {
            $table->decimal('rimborsato_importo', 10, 2)->nullable()->after('pagato_at');
            $table->timestamp('rimborsato_at')->nullable()->after('rimborsato_importo');
        });
    }

    public function down(): void
    {
        Schema::table('ordini_b2b', function (Blueprint $table) {
            $table->dropColumn(['rimborsato_importo', 'rimborsato_at']);
        });
    }
};
