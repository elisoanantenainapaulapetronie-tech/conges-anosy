<?php

use App\Http\Controllers\OrganigrammeController;
use App\Http\Controllers\Rh\PosteController;
use Illuminate\Support\Facades\Route;

/*
 * Organigramme (lot 4). Inclus depuis routes/web.php par :  require __DIR__.'/organigramme.php';
 */
Route::middleware('auth')->get('/organigramme', [OrganigrammeController::class, 'index'])->name('organigramme');

// Postes : RH (la modification est réservée au RH responsable, vérifiée dans le contrôleur).
Route::middleware(['auth', 'role:rh_gestionnaire,rh_responsable'])->prefix('rh')->name('rh.')->group(function () {
    Route::get('/postes/nouveau', [PosteController::class, 'create'])->name('postes.create');
    Route::post('/postes', [PosteController::class, 'store'])->name('postes.store');
    Route::get('/postes/{poste}/modifier', [PosteController::class, 'edit'])->whereNumber('poste')->name('postes.edit');
    Route::put('/postes/{poste}', [PosteController::class, 'update'])->whereNumber('poste')->name('postes.update');
    Route::post('/postes/{poste}/retirer', [PosteController::class, 'retirer'])->whereNumber('poste')->name('postes.retirer');
});
