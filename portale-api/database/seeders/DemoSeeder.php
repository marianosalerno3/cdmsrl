<?php

namespace Database\Seeders;

use App\Enums\ListinoTipo;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RichiestaStato;
use App\Models\Agente;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Colore;
use App\Models\Genere;
use App\Models\OrdineB2B;
use App\Models\Prodotto;
use App\Models\Stagione;
use App\Models\Taglia;
use App\Models\VarianteProdotto;
use App\Services\PriceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dati dimostrativi per review/QA locale.
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $prices = app(PriceService::class);

        $categorie = collect(['ABITO', 'GIACCA', 'MAGLIA', 'PANTALONE', 'CAMICIA', 'GONNA', 'BORSA', 'CINTURA'])
            ->map(fn ($n) => Categoria::firstOrCreate(['nome' => $n]));
        $generi = collect(['Donna', 'Uomo'])->map(fn ($n) => Genere::firstOrCreate(['nome' => $n]));
        $colori = Colore::all();
        if ($colori->count() < 6) {
            $colori = collect(['NERO', 'BIANCO', 'BLU', 'ROSSO', 'VERDE', 'BEIGE', 'PANNA', 'FANTASIA'])
                ->map(fn ($n) => Colore::firstOrCreate(['nome' => $n]));
        }
        $taglie = Taglia::all();
        $stagioni = Stagione::whereIn('codice', ['PE25', 'AI25', 'PE26'])->get();
        if ($stagioni->isEmpty()) {
            $stagioni = collect(['PE25', 'AI25', 'PE26'])->map(fn ($c) => Stagione::firstOrCreate(['codice' => $c], ['attiva' => true]));
        }

        // --- Prodotti + varianti ---
        $prodotti = collect();
        foreach (range(1, 24) as $i) {
            $cat = $categorie->random();
            $stag = $stagioni->random();
            $p = Prodotto::create([
                'codice' => sprintf('%s%03d/%02d', substr($cat->nome, 0, 1), $i, random_int(1, 9)),
                'nome' => "{$cat->nome} ".Str::title(fake()->words(2, true)),
                'descrizione' => fake()->sentence(10),
                'composizione' => fake()->randomElement(['100% cotone', '80% viscosa 20% lino', '95% poliestere 5% elastan']),
                'tessuto' => fake()->randomElement(['popeline', 'jersey', 'rafia', 'crepe', 'denim']),
                'tipo' => 'variabile',
                'categoria_id' => $cat->id,
                'stagione_id' => $stag->id,
                'genere_id' => $generi->random()->id,
                'attivo' => fake()->boolean(85),
            ]);

            $combos = collect();
            foreach ($taglie->random(min(4, $taglie->count())) as $t) {
                foreach ($colori->random(random_int(1, 3)) as $c) {
                    $combos->push([$t->id, $c->id]);
                }
            }
            $prezzo = fake()->randomElement([9.9, 14.9, 19.9, 29.9, 39.9, 49.9, 79]);
            foreach ($combos->unique(fn ($x) => $x[0].$x[1]) as $combo) {
                VarianteProdotto::create([
                    'prodotto_id' => $p->id,
                    'taglia_id' => $combo[0],
                    'colore_id' => $combo[1],
                    'sku' => strtoupper(Str::random(3)).random_int(1000, 9999),
                    'prezzo' => $prezzo,
                    'quantita' => fake()->numberBetween(0, 40),
                ]);
            }
            $prodotti->push($p->load('varianti'));
        }

        // --- Agenti + clienti ---
        $agenti = collect();
        foreach (range(1, 5) as $i) {
            $a = Agente::create([
                'codice_agente' => 'AG'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'nome' => fake()->company(),
                'email' => "agente{$i}@demo.test",
                'password' => Hash::make('password'),
                'listino_default' => fake()->randomElement([ListinoTipo::Standard, ListinoTipo::Plus5]),
                'commissione_perc' => fake()->randomElement([8, 10, 12]),
                'attivo' => true,
            ]);
            $agenti->push($a);

            foreach (range(1, random_int(3, 6)) as $j) {
                Cliente::create([
                    'agente_id' => $a->id,
                    'tipo' => 'b2b',
                    'ragione_sociale' => strtoupper(fake()->company()).' '.fake()->companySuffix(),
                    'partita_iva' => 'IT'.fake()->numerify('###########'),
                    'email' => fake()->companyEmail(),
                    'stato_richiesta' => fake()->boolean(80) ? RichiestaStato::Approvato : RichiestaStato::InAttesa,
                    'tipo_listino' => fake()->randomElement([ListinoTipo::Standard, ListinoTipo::Plus5, null]),
                    'attivo' => true,
                    'contrassegno_abilitato' => fake()->boolean(30),
                    'indirizzo' => fake()->streetAddress(),
                    'cap' => fake()->postcode(),
                    'citta' => fake()->city(),
                    'provincia' => fake()->randomElement(['MI', 'RM', 'NA', 'TO', 'BA', 'FI']),
                ]);
            }
        }

        // --- Ordini ---
        $clienti = Cliente::where('stato_richiesta', RichiestaStato::Approvato)->get();
        foreach (range(1, 45) as $i) {
            $cliente = $clienti->random();
            $listino = $cliente->listinoEffettivo();
            $data = fake()->dateTimeBetween('-6 months', 'now');

            $righeInput = [];
            foreach ($prodotti->random(random_int(2, 6)) as $p) {
                $v = $p->varianti->random();
                $q = random_int(2, 12);
                $prezzo = $prices->forVariant($v, $listino);
                $righeInput[] = [
                    'variante_prodotto_id' => $v->id,
                    'prodotto_codice' => $p->codice,
                    'prodotto_nome' => $p->nome,
                    'taglia' => $v->taglia?->nome,
                    'colore' => $v->colore?->nome,
                    'sku' => $v->sku,
                    'quantita' => $q,
                    'prezzo_unitario' => $prezzo,
                    'totale_riga' => round($prezzo * $q, 2),
                ];
            }
            $totali = $prices->totals($righeInput);

            $ordine = OrdineB2B::create([
                'numero' => sprintf('ORD-%s-%04d', $data->format('Ymd'), $i),
                'agente_id' => $cliente->agente_id,
                'cliente_id' => $cliente->id,
                'cliente_nome' => $cliente->denominazione,
                'cliente_email' => $cliente->email,
                'indirizzo_spedizione' => "{$cliente->indirizzo}, {$cliente->cap} {$cliente->citta} ({$cliente->provincia})",
                'stato' => fake()->randomElement(OrderStatus::cases()),
                'metodo_pagamento' => fake()->randomElement(PaymentMethod::cases()),
                'listino_applicato' => $listino,
                'note_agente' => fake()->boolean(30) ? fake()->sentence() : null,
                'data_ordine' => $data->format('Y-m-d'),
                'email_conferma_inviata_at' => fake()->boolean(50) ? $data : null,
                ...$totali,
            ]);
            $ordine->righe()->createMany($righeInput);
        }
    }
}
