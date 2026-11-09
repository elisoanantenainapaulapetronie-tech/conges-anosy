<?php

use App\Http\Controllers\Rh\JourFerieController;
use App\Http\Controllers\Rh\SoldeRhController;
use App\Http\Controllers\SoldeController;
use Illuminate\Support\Facades\Route;

/*
 * Soldes et décompte (lot 5). Inclus depuis routes/web.php par :  require __DIR__.'/soldes.php';
 */

// Tout agent connecté : son propre solde, et la simulation de durée.
Route::middleware('auth')->group(function () {
    Route::get('/solde', [SoldeController::class, 'index'])->name('solde');
    Route::post('/decompte/simuler', [SoldeController::class, 'simuler'])->middleware('throttle:60,1')->name('decompte.simuler');
});

// Espace RH : chaque action vérifie en plus son droit précis (SoldePolicy).
Route::middleware(['auth', 'role:rh_gestionnaire,rh_responsable'])->prefix('rh')->name('rh.')->group(function () {
    Route::get('/soldes', [SoldeRhController::class, 'index'])->name('soldes.index');
    Route::get('/soldes/propositions', [SoldeRhController::class, 'propositions'])->name('soldes.propositions');
    Route::post('/soldes/crediter', [SoldeRhController::class, 'crediter'])->name('soldes.crediter');
    Route::post('/soldes/propositions/{proposition}/controler', [SoldeRhController::class, 'controler'])->whereNumber('proposition')->name('soldes.controler');
    Route::get('/soldes/{agent}', [SoldeRhController::class, 'show'])->whereNumber('agent')->name('soldes.show');
    Route::post('/soldes/{agent}/proposer', [SoldeRhController::class, 'proposer'])->whereNumber('agent')->name('soldes.proposer');

    Route::get('/jours-feries', [JourFerieController::class, 'index'])->name('jours-feries.index');
    Route::post('/jours-feries', [JourFerieController::class, 'store'])->name('jours-feries.store');
    Route::post('/jours-feries/remplir', [JourFerieController::class, 'remplir'])->name('jours-feries.remplir');
    Route::delete('/jours-feries/{jour}', [JourFerieController::class, 'destroy'])->whereNumber('jour')->name('jours-feries.destroy');
});
