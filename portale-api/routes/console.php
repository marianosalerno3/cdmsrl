<?php

use Illuminate\Support\Facades\Schedule;

// Import prodotti/varianti/prezzi/giacenze da WinMino, poi push verso e-commerce SOLO di ciò che è cambiato
// (giacenze: push leggero; prodotto nuovo o con dati/prezzi cambiati: push completo).
Schedule::command('sync:prodotti --push')->hourly()->withoutOverlapping();

// Import anagrafica clienti da WinMino.
Schedule::command('sync:clienti')->dailyAt('03:00');

// Push giacenze verso i canali e-commerce abilitati (rete di sicurezza tra un import e l'altro).
// Giornaliero: con il limite Shopify di 2 chiamate/s un giro completo dura ore, ogni 30 minuti la coda non si svuoterebbe mai.
// Le variazioni di giacenza dell'import le accoda l'import stesso (--push): l'observer delle varianti non scatta
// durante l'import (salvataggi senza eventi), quindi questo giro notturno e' la rete di sicurezza.
Schedule::command('sync:giacenze')->dailyAt('04:00')->withoutOverlapping();

// Promemoria ai clienti per i carrelli fermi (solo con consenso alle comunicazioni; vedi carrelli:promemoria).
Schedule::command('carrelli:promemoria')->hourly()->withoutOverlapping();
