<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
 * Centre de notifications (lot 5 quinquies). Inclus depuis routes/web.php par :  require __DIR__.'/notifications.php';
 * Tout agent connecté ; chacun ne voit que ses propres notifications.
 */
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/compteur', [NotificationController::class, 'compteur'])->name('notifications.compteur');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.tout-lire');
    Route::get('/notifications/{notification}/ouvrir', [NotificationController::class, 'ouvrir'])->whereNumber('notification')->name('notifications.ouvrir');
    Route::post('/notifications/{notification}/lue', [NotificationController::class, 'marquerLue'])->whereNumber('notification')->name('notifications.lue');
});
