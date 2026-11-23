<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ogni pacchetto appartiene a una linea (Classe di Valentina, Clara G…): nel catalogo, scelta la linea,
 * si vedono solo i suoi pacchetti. Il nome è unico dentro la linea, non più in assoluto.
 *
 * Sui dati già presenti la linea si ricava dai prodotti del pacchetto; un pacchetto con prodotti di più linee
 * (es. "BASICI A COLORI") viene separato: resta alla prima linea e ne nasce uno gemello per ogni altra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacchetti', function (Blueprint $table) {
            $table->string('linea')->nullable()->after('nome')->index();
            $table->dropUnique(['nome']);
            $table->unique(['nome', 'linea']);
        });

        foreach (DB::table('pacchetti')->orderBy('ordine')->get() as $pacchetto) {
            $linee = DB::table('prodotti')->where('pacchetto_id', $pacchetto->id)
                ->whereNotNull('linea')->distinct()->orderBy('linea')->pluck('linea')->all();

            if ($linee === []) {
                continue; // pacchetto vuoto: la linea la sceglie CDM dal pannello
            }

            DB::table('pacchetti')->where('id', $pacchetto->id)->update(['linea' => $linee[0]]);

            foreach (array_slice($linee, 1) as $altra) {
                $nuovo = (string) Str::uuid7();
                DB::table('pacchetti')->insert([
                    'id' => $nuovo, 'nome' => $pacchetto->nome, 'linea' => $altra,
                    'ordine' => (int) DB::table('pacchetti')->max('ordine') + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('prodotti')->where('pacchetto_id', $pacchetto->id)->where('linea', $altra)
                    ->update(['pacchetto_id' => $nuovo]);
            }
        }
    }

    public function down(): void
    {
        // i pacchetti gemelli (stesso nome, linee diverse) vanno riuniti prima di ripristinare l'unicità del nome
        foreach (DB::table('pacchetti')->select('nome')->groupBy('nome')->havingRaw('count(*) > 1')->pluck('nome') as $nome) {
            $ids = DB::table('pacchetti')->where('nome', $nome)->orderBy('ordine')->pluck('id');
            DB::table('prodotti')->whereIn('pacchetto_id', $ids->slice(1)->all())->update(['pacchetto_id' => $ids->first()]);
            DB::table('pacchetti')->whereIn('id', $ids->slice(1)->all())->delete();
        }

        Schema::table('pacchetti', function (Blueprint $table) {
            $table->dropUnique(['nome', 'linea']);
            $table->dropIndex(['linea']);
            $table->dropColumn('linea');
            $table->unique('nome');
        });
    }
};
