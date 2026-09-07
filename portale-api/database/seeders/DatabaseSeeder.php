<?php

namespace Database\Seeders;

use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Models\Agente;
use App\Models\AppConfig;
use App\Models\Cliente;
use App\Models\Colore;
use App\Models\Stagione;
use App\Models\Taglia;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Utente pannello admin ---
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Amministratore', 'password' => Hash::make('password'), 'is_active' => true],
        );

        // --- Config business (default osservati nell'originale) ---
        AppConfig::put('vat', 22);
        AppConfig::put('spese_spedizione', 15);
        AppConfig::put('importo_minimo_ordine', 0);
        AppConfig::put('giorni_evasione_programmati', 30);
        AppConfig::put('metodi_pagamento', ['stripe', 'bonifico', 'contrassegno']);

        // --- Taglie ---
        foreach (['38', '40', '42', '44', '46', '48', '50', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'UN', 'UNICA'] as $i => $nome) {
            Taglia::updateOrCreate(['nome' => $nome], ['ordine' => $i]);
        }

        // --- Colori (seed minimo; il dizionario completo si importa da ERP/CSV) ---
        foreach (['NERO', 'BIANCO', 'BLU', 'ROSSO', 'VERDE', 'BEIGE', 'PANNA', 'FANTASIA'] as $nome) {
            Colore::updateOrCreate(['nome' => $nome], []);
        }

        // --- Stagioni ---
        foreach ([['PE25', 4], ['AI25', 3], ['PE26', 2], ['AI26', 1]] as [$codice, $ordine]) {
            Stagione::updateOrCreate(['codice' => $codice], ['attiva' => true, 'ordine' => $ordine]);
        }
        Stagione::updateOrCreate(['codice' => 'Programmato'], ['attiva' => true, 'programmata' => true, 'ordine' => 0]);

        // --- Agente demo (login SPA: agente@example.com / password) ---
        $agente = Agente::updateOrCreate(
            ['email' => 'agente@example.com'],
            [
                'codice_agente' => 'A001',
                'nome' => 'Agente Demo',
                'password' => Hash::make('password'),
                'listino_default' => ListinoTipo::Standard,
                'commissione_perc' => 10,
                'attivo' => true,
            ],
        );

        Cliente::updateOrCreate(
            ['email' => 'cliente@example.com'],
            [
                'agente_id' => $agente->id,
                'tipo' => 'b2b',
                'ragione_sociale' => 'BOUTIQUE DEMO SRL',
                'partita_iva' => 'IT01234567890',
                'stato_richiesta' => RichiestaStato::Approvato,
                'tipo_listino' => ListinoTipo::Standard,
                'attivo' => true,
                'indirizzo' => 'Via Roma 1',
                'cap' => '20100',
                'citta' => 'Milano',
                'provincia' => 'MI',
            ],
        );
    }
}
