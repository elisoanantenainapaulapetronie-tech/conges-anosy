<?php

use App\Http\Controllers\Rh\CompteController;
use App\Http\Controllers\Rh\ParametreProvisoireController;
use Illuminate\Support\Facades\Route;

/*
 * Lot 6 : lien de réinitialisation remis par la RH, confirmation des valeurs provisoires.
 * Inclus depuis routes/web.php par :  require __DIR__.'/continuite.php';
 */
Route::middleware(['auth', 'role:rh_gestionnaire,rh_responsable'])->prefix('rh')->name('rh.')->group(function () {
    Route::post('/agents/{agent}/compte/lien', [CompteController::class, 'genererLien'])->whereNumber('agent')
        ->middleware('throttle:20,1')->name('comptes.lien');
    Route::post('/parametres/{cle}/confirmer', [ParametreProvisoireController::class, 'confirmer'])
        ->where('cle', '[a-z0-9_]+')->name('parametres.confirmer');
});
