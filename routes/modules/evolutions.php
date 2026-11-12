<?php

use App\Http\Controllers\Evolutions\ApiKeyController;
use App\Http\Controllers\Evolutions\MessagesAutoController;
use App\Http\Controllers\Evolutions\PrescripteurApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    Route::match(['get', 'post'], 'messages_automatiques', [MessagesAutoController::class, 'index']);
    Route::post('gestion_promoteurs/cle/{id}', [ApiKeyController::class, 'generate'])->whereNumber('id');
    Route::post('gestion_promoteurs/cle/{id}/revoquer', [ApiKeyController::class, 'revoke'])->whereNumber('id');
});

Route::middleware('throttle:600,1')->group(function () {
    Route::get('api/v1/documentation', [ApiKeyController::class, 'documentation']);
    Route::get('api/v1/lots', [PrescripteurApiController::class, 'lots']);
    Route::get('api/v1/denonciations', [PrescripteurApiController::class, 'denonciations']);
});
