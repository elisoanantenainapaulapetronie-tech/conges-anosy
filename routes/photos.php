<?php

use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\Rh\PhotoAgentController;
use Illuminate\Support\Facades\Route;

/*
 * Photo de profil. Inclus depuis routes/web.php par :  require __DIR__.'/photos.php';
 */
Route::middleware('auth')->group(function () {
    // Mon profil (tous les espaces)
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::post('/profil/photo', [ProfilController::class, 'enregistrer'])->middleware('throttle:10,1')->name('profil.photo.enregistrer');
    Route::delete('/profil/photo', [ProfilController::class, 'supprimer'])->name('profil.photo.supprimer');

    // Affichage d'une photo (contrôle d'accès dans le contrôleur)
    Route::get('/agents/{agent}/photo', [PhotoController::class, 'afficher'])->whereNumber('agent')->name('photo.afficher');

    // Espace RH : photo d'un autre agent
    Route::middleware('role:rh_gestionnaire,rh_responsable')->prefix('rh')->name('rh.')->group(function () {
        Route::post('/agents/{agent}/photo', [PhotoAgentController::class, 'enregistrer'])->whereNumber('agent')
            ->middleware('throttle:20,1')->name('agents.photo.enregistrer');
        Route::delete('/agents/{agent}/photo', [PhotoAgentController::class, 'supprimer'])->whereNumber('agent')
            ->name('agents.photo.supprimer');
    });
});
