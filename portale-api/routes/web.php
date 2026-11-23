<?php

use Illuminate\Support\Facades\Route;

// Il pannello admin è montato da App\Providers\Filament\AccessPanelProvider su /access.
Route::get('/', fn () => redirect(config('portale.spa_url')));

// Annullamento iscrizione alle comunicazioni commerciali (link firmato dentro le email).
Route::get("/comunicazioni/annulla/{cliente}", [\App\Http\Controllers\ComunicazioniController::class, "annulla"])
    ->middleware("signed")
    ->name("comunicazioni.annulla");
