<?php

use App\Http\Controllers\DemandeController;
use Illuminate\Support\Facades\Route;


Route::middleware('auth')->prefix('demandes')->name('demandes.')->group(function(){
    Route::get('/', [DemandeController::class, 'index'])->name('index');
    Route::get('/nouvelle', [DemandeController::class, 'create'])->name('create');
    Route::post('/', [DemandeController::class, 'store'])->name('store');
    Route::get('/{demande}', [DemandeController::class, 'show'])->whereNumber('demande')->name('show');
    Route::get('/{demande}/modifier', [DemandeController::class, 'edit'])->whereNumber('demande')->name('edit');
    Route::put('/{demande}', [DemandeController::class, 'update'])->whereNumber('demande')->name('update');
    Route::post('/{demande}/soumettre', [DemandeController::class, 'soumettre'])->whereNumber('demande')->name('soumettre');
});