<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codice_agente')->unique();       // codice gestionale/ERP
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('password')->nullable();          // auth API (Sanctum) — nullable finché non invitato
            $table->string('telefono')->nullable();
            $table->string('listino_default')->default('standard'); // App\Enums\ListinoTipo
            $table->decimal('commissione_perc', 5, 2)->default(0);
            $table->boolean('attivo')->default(true);
            $table->string('codice_erp')->nullable();
            $table->timestamp('sincronizzato_erp_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenti');
    }
};
