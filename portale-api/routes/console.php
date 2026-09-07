<?php

use Illuminate\Support\Facades\Schedule;

// Sync giacenze verso e-commerce (se abilitato nei settings).
Schedule::command('sync:giacenze')->everyThirtyMinutes()->withoutOverlapping();

// Import anagrafica clienti da ERP.
Schedule::command('sync:clienti')->dailyAt('03:00');
