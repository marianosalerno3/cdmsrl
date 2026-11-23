<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Token di reset password dei clienti B2B (tabella separata da quella di agenti e admin: stessa email = utenti diversi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_password_reset_tokens');
    }
};
