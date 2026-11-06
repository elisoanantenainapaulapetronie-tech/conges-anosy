<?php

use App\Http\Controllers\Rh\AgentController;
use App\Http\Controllers\Rh\CompteController;
use App\Http\Controllers\Rh\HabilitationController;
use App\Http\Controllers\Rh\StructureController;
use App\Http\Controllers\Rh\TableauRhController;
use Illuminate\Support\Facades\Route;

/*
 * Espace RH (lot 1 : organisation).
 * Inclus depuis routes/web.php par :  require __DIR__.'/rh.php';
 * L'accès est limité aux rôles RH ; chaque action vérifie en plus son droit précis (policies).
 */
Route::middleware(['auth', 'role:rh_gestionnaire,rh_responsable'])->group(function () {

    Route::get('/rh', TableauRhController::class)->name('rh');

    Route::prefix('rh')->name('rh.')->group(function () {
        // Agents
        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('/agents/nouveau', [AgentController::class, 'create'])->name('agents.create');
        Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
        Route::get('/agents/{agent}', [AgentController::class, 'show'])->whereNumber('agent')->name('agents.show');
        Route::get('/agents/{agent}/modifier', [AgentController::class, 'edit'])->whereNumber('agent')->name('agents.edit');
        Route::put('/agents/{agent}', [AgentController::class, 'update'])->whereNumber('agent')->name('agents.update');

        // Compte de connexion de l'agent
        Route::post('/agents/{agent}/compte/code', [CompteController::class, 'genererCode'])->whereNumber('agent')
            ->middleware('throttle:20,1')->name('comptes.code');
        Route::post('/agents/{agent}/compte/desactiver', [CompteController::class, 'desactiver'])->whereNumber('agent')->name('comptes.desactiver');
        Route::post('/agents/{agent}/compte/reactiver', [CompteController::class, 'reactiver'])->whereNumber('agent')->name('comptes.reactiver');

        // Rôles (habilitations)
        Route::post('/agents/{agent}/habilitations', [HabilitationController::class, 'store'])->whereNumber('agent')->name('habilitations.store');
        Route::delete('/agents/{agent}/habilitations/{habilitation}', [HabilitationController::class, 'retirer'])
            ->whereNumber(['agent', 'habilitation'])->name('habilitations.retirer');

        // Structures
        Route::get('/structures', [StructureController::class, 'index'])->name('structures.index');
        Route::get('/structures/nouvelle', [StructureController::class, 'create'])->name('structures.create');
        Route::post('/structures', [StructureController::class, 'store'])->name('structures.store');
        Route::get('/structures/{structure}/modifier', [StructureController::class, 'edit'])->whereNumber('structure')->name('structures.edit');
        Route::put('/structures/{structure}', [StructureController::class, 'update'])->whereNumber('structure')->name('structures.update');
        Route::post('/structures/{structure}/etat', [StructureController::class, 'etat'])->whereNumber('structure')->name('structures.etat');
    });
});
