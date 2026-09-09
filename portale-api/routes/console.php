<?php

use Illuminate\Support\Facades\Schedule;

// Push giacenze verso i canali e-commerce abilitati.
Schedule::command('sync:giacenze')->everyThirtyMinutes()->withoutOverlapping();

// Import ordini dai canali e-commerce.
Schedule::command('orders:pull-ecommerce')->everyFifteenMinutes()->withoutOverlapping();

// Import anagrafica prodotti/clienti da ERP (WinMino).
Schedule::command('sync:clienti')->dailyAt('03:00');
