<?php

use Illuminate\Support\Facades\Schedule;

// Import prodotti/varianti/prezzi/giacenze da WinMino, poi push verso e-commerce.
Schedule::command('sync:prodotti --push')->hourly()->withoutOverlapping();

// Import anagrafica clienti da WinMino.
Schedule::command('sync:clienti')->dailyAt('03:00');

// Push giacenze verso i canali e-commerce abilitati (rete di sicurezza tra un import e l'altro).
Schedule::command('sync:giacenze')->everyThirtyMinutes()->withoutOverlapping();

// Import ordini dai canali e-commerce (tab "Ordini Shopify").
Schedule::command('orders:pull-ecommerce')->everyFifteenMinutes()->withoutOverlapping();
