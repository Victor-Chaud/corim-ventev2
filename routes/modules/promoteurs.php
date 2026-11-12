<?php

use App\Http\Controllers\Promoteurs\JsonController;
use App\Http\Controllers\Promoteurs\PromoteursController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    Route::match(['get', 'post'], 'gestion_promoteurs/{index?}', [PromoteursController::class, 'index'])->where('index', 'index');
    Route::match(['get', 'post'], 'gestion_promoteurs/actifs', [PromoteursController::class, 'actifs']);
    Route::match(['get', 'post'], 'gestion_promoteurs/inactifs', [PromoteursController::class, 'inactifs']);
    Route::match(['get', 'post'], 'gestion_promoteurs/modifier/{id?}', [PromoteursController::class, 'modifier']);

    Route::match(['get', 'post'], 'json/AddPromoteur', [JsonController::class, 'add']);
    Route::match(['get', 'post'], 'json/DeletePromoteur', [JsonController::class, 'deactivate']);
    Route::match(['get', 'post'], 'json/ActivPromoteur', [JsonController::class, 'activate']);
    Route::match(['get', 'post'], 'json/TrashPromoteur', [JsonController::class, 'destroy']);
});
