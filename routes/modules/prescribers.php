<?php

use App\Http\Controllers\Prescribers\JsonController;
use App\Http\Controllers\Prescribers\PrescribersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    Route::match(['get', 'post'], 'gestion_promoteurs/{index?}', [PrescribersController::class, 'index'])->where('index', 'index');
    Route::match(['get', 'post'], 'gestion_promoteurs/actifs', [PrescribersController::class, 'active']);
    Route::match(['get', 'post'], 'gestion_promoteurs/inactifs', [PrescribersController::class, 'inactive']);
    Route::match(['get', 'post'], 'gestion_promoteurs/modifier/{id?}', [PrescribersController::class, 'edit']);
    Route::post('gestion_promoteurs/reinitialisation/{id}', [PrescribersController::class, 'sendResetLink'])->whereNumber('id');

    Route::match(['get', 'post'], 'json/AddPromoteur', [JsonController::class, 'add']);
    Route::match(['get', 'post'], 'json/DeletePromoteur', [JsonController::class, 'deactivate']);
    Route::match(['get', 'post'], 'json/ActivPromoteur', [JsonController::class, 'activate']);
    Route::match(['get', 'post'], 'json/TrashPromoteur', [JsonController::class, 'destroy']);
});
