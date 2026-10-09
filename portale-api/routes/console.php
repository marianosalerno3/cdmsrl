<?php

use Illuminate\Support\Facades\Schedule;

// Import prodotti/varianti/prezzi/giacenze da WinMino, poi push verso e-commerce.
Schedule::command('sync:prodotti --push')->hourly()->withoutOverlapping();

// Import anagrafica clienti da WinMino.
Schedule::command('sync:clienti')->dailyAt('03:00');

// Push giacenze verso i canali e-commerce abilitati (rete di sicurezza tra un import e l'altro).
// Giornaliero: con il limite Shopify di 2 chiamate/s un giro completo dura ore, ogni 30 minuti la coda non si svuoterebbe mai.
// Le variazioni di giacenza sono gia' propagate al volo dall'observer delle varianti.
Schedule::command('sync:giacenze')->dailyAt('04:00')->withoutOverlapping();

// Import ordini dai canali e-commerce (tab "Ordini Shopify").
Schedule::command('orders:pull-ecommerce')->everyFifteenMinutes()->withoutOverlapping();
