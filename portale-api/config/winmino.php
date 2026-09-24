<?php

/*
| Configurazione integrazione WinMino (ERP).
| Le credenziali/host sono in App\Settings\IntegrationSettings (pannello, cifrate).
| Qui stanno i default statici e le mappe di dominio (codici WinMino <-> enum portale).
|
| I valori "TODO" vanno confermati con CDM/Magis (vedi docs/winmino-integration.md, punti aperti).
*/

use App\Enums\ListinoTipo;
use App\Enums\PaymentMethod;

return [

    // Moduli DataSnap
    'modules' => [
        'get' => env('WINMINO_MODULE_GET', 'TsmStandard'),
        'clienti' => 'TDSClientiServerModule',
        'ordini' => 'TDSOrdiniCLienti', // casing come da documentazione
    ],

    // Prefisso formato per le GET: JSO (compatto) | JSS | XML
    'get_prefix' => 'JSO',

    // Formati richiesti dai webservice
    'date_format' => 'd-m-Y',
    'decimal_separator' => '.',

    // Timeout HTTP (secondi) e retry
    'http_timeout' => (int) env('WINMINO_HTTP_TIMEOUT', 120), // GetArticoli pesa ~6,5 MB e impiega 20–40 s
    'http_retries' => 2,
    'http_retry_sleep_ms' => 800,

    // Import catalogo WinMino → portale: SOLO ciò che CDM decide di portare sul portale.
    'import' => [
        // selezioni LINEA:STAGIONE[:LISTINO] (codici WinMino), separate da virgola.
        // Es. "CG:PE27:CLARAG,OC:PE27:OT". Senza LISTINO vale `listino_base`. Vuoto = non importa nulla.
        'selezioni' => array_values(array_filter(array_map('trim', explode(',', (string) env('WINMINO_IMPORT_SELEZIONI', 'CG:PE27:CLARAG,OC:PE27:OT'))))),

        // Listino di default per le selezioni che non ne indicano uno; il portale applica poi i ricarichi (plus5 ecc.).
        // Gli articoli senza prezzo nel listino della loro selezione NON vengono importati.
        'listino_base' => env('WINMINO_LISTINO_BASE', 'CLARAG'),

        // Depositi da cui leggere la giacenza (codici WinMino, virgola). Se più di uno vengono sommati;
        // vuoto = tutti. CDM: SOLO il deposito DG (gli altri non sono vendibili).
        'depositi' => array_values(array_filter(array_map('trim', explode(',', (string) env('WINMINO_DEPOSITI_GIACENZA', 'DG'))))),
    ],

    // Default di testata ordine
    'defaults' => [
        'unita' => env('WINMINO_UNITA', 'NR'),      // TODO confermare (unità di misura riga)
        'divisa' => env('WINMINO_DIVISA', 'EUR'),    // TODO
        'nazione' => env('WINMINO_NAZIONE', 'IT'),   // TODO codice nazione WinMino
        'vettore' => env('WINMINO_VETTORE', ''),     // TODO
        'porto' => env('WINMINO_PORTO', ''),         // TODO
        'dicampionario' => 0,
    ],

    // enum portale -> codice listino WinMino (VARCHAR 6)  — TODO confermare i codici
    'listino_map' => [
        ListinoTipo::Standard->value => env('WINMINO_LISTINO_STANDARD', env('WINMINO_LISTINO_BASE', 'CLARAG')),
        ListinoTipo::Plus5->value => env('WINMINO_LISTINO_PLUS5', 'PLUS5'),
        ListinoTipo::Plus10->value => env('WINMINO_LISTINO_PLUS10', 'PLUS10'),
        ListinoTipo::Outlet->value => env('WINMINO_LISTINO_OUTLET', 'OUT'),
    ],

    // enum portale -> codice pagamento WinMino (VARCHAR 6) — TODO confermare i codici
    'pagamento_map' => [
        PaymentMethod::Bonifico->value => env('WINMINO_PAG_BONIFICO', 'BB30'),
        PaymentMethod::Contrassegno->value => env('WINMINO_PAG_CONTRASSEGNO', 'CONTR'),
        PaymentMethod::Rimessa->value => env('WINMINO_PAG_RIMESSA', 'RD'),
        PaymentMethod::Stripe->value => env('WINMINO_PAG_STRIPE', 'CARTA'),
    ],

    // criterio sconto di default per i clienti creati dal portale (N|A|S|L)
    'criterio_sconto_default' => 'N',
];
