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
    'http_timeout' => 30,
    'http_retries' => 2,
    'http_retry_sleep_ms' => 800,

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
        ListinoTipo::Standard->value => env('WINMINO_LISTINO_STANDARD', 'STD'),
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
