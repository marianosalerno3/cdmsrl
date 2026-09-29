<?php

use Illuminate\Support\Facades\Route;

// Il pannello admin è montato da App\Providers\Filament\AccessPanelProvider su /access.
Route::get('/', fn () => redirect(config('portale.spa_url')));
