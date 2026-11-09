<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/accueil');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'formulaire'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'connecter'])->middleware('throttle:10,1');

    Route::get('/activation', [ActivationController::class, 'formulaire'])->name('activation');
    Route::post('/activation', [ActivationController::class, 'activer'])->middleware('throttle:10,1');

    Route::get('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'formulaire'])->name('mot-de-passe-oublie');
    Route::post('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'envoyer'])->middleware('throttle:10,1');
    Route::get('/mot-de-passe-oublie/envoye', [MotDePasseOublieController::class, 'confirmation'])->name('mot-de-passe-oublie.envoye');

    Route::get('/reinitialisation/{jeton}', [MotDePasseOublieController::class, 'formulaireReinitialisation'])
        ->middleware('throttle:20,1')->name('reinitialisation');
    Route::post('/reinitialisation/{jeton}', [MotDePasseOublieController::class, 'appliquer'])
        ->middleware('throttle:10,1')->name('reinitialisation.appliquer');
});

Route::middleware('auth')->group(function () {
    Route::post(
    '/session/prolonger',
    fn () => response()->noContent()
)->name('session.prolonger');
    Route::get('/accueil', [AccueilController::class, 'index'])->name('accueil');
    Route::post('/deconnexion', [ConnexionController::class, 'deconnecter'])->name('deconnexion');

    // Exemple de route protégée par rôle (à remplacer par l'espace RH) :
    require __DIR__.'/rh.php';
     require __DIR__.'/photos.php';
     require __DIR__.'/organigramme.php';
 require __DIR__.'/soldes.php';



});
