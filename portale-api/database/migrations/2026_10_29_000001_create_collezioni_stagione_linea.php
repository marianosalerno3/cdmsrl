<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Collezione" = stagione + linea (es. Classe di Valentina PE27). Pronto o programmato si decide qui,
 * a mano; per le programmate si indica poi la data di partenza delle spedizioni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collezioni', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stagione_id')->constrained('stagioni')->cascadeOnDelete();
            $table->string('linea');
            $table->boolean('programmata')->default(false);
            $table->date('data_partenza')->nullable();
            $table->timestamps();
            $table->unique(['stagione_id', 'linea']);
        });

        // una collezione per ogni coppia stagione + linea già presente nel catalogo; chi era programmato resta tale
        $stagioni = DB::table('stagioni')->get()->keyBy('id');
        $coppie = DB::table('prodotti')->whereNull('deleted_at')->whereNotNull('stagione_id')->whereNotNull('linea')
            ->select('stagione_id', 'linea')->distinct()->get();

        foreach ($coppie as $c) {
            $stagione = $stagioni[$c->stagione_id] ?? null;
            if (! $stagione) {
                continue;
            }
            $programmata = (bool) $stagione->programmata;
            foreach ((array) json_decode((string) ($stagione->programmata_linee ?? ''), true) as $eccezione) {
                if (strtoupper(trim((string) ($eccezione['linea'] ?? ''))) === strtoupper(trim($c->linea))) {
                    $programmata = (bool) ($eccezione['programmata'] ?? false);
                }
            }
            DB::table('collezioni')->insert([
                'id' => (string) Str::uuid7(), 'stagione_id' => $c->stagione_id, 'linea' => $c->linea,
                'programmata' => $programmata, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (Schema::hasColumn('stagioni', 'programmata_linee')) {
            Schema::table('stagioni', fn (Blueprint $table) => $table->dropColumn('programmata_linee'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collezioni');
    }
};
