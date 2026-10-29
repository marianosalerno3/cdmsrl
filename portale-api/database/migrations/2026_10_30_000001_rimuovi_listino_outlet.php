<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il listino Outlet (×0,60) non si usa più. Chi lo aveva torna al listino base: agenti e ordini a "standard",
 * i clienti con listino proprio tornano a quello dell'agente (vuoto). Senza questo il valore 'outlet'
 * non corrisponderebbe più a nessun listino e le schede andrebbero in errore.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('agenti')->where('listino_default', 'outlet')->update(['listino_default' => 'standard']);
        DB::table('clienti')->where('tipo_listino', 'outlet')->update(['tipo_listino' => null]);
        DB::table('ordini_b2b')->where('listino_applicato', 'outlet')->update(['listino_applicato' => 'standard']);
    }

    public function down(): void
    {
        // i valori originali non si ricostruiscono
    }
};
