<?php

use App\Http\Controllers\ClientsProspects\ClientsController;
use App\Http\Controllers\ClientsProspects\ProspectsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'json/SearchProspect', [ProspectsController::class, 'search']);
    Route::match(['get', 'post'], 'json/AddProspect', [ProspectsController::class, 'add']);

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'gestion_prospects/{index?}', [ProspectsController::class, 'index'])->where('index', 'index');

        Route::match(['get', 'post'], 'gestion_clients/{index?}', [ClientsController::class, 'index'])->where('index', 'index');
        Route::match(['get', 'post'], 'gestion_clients/ajouter_client', [ClientsController::class, 'ajouter']);
        Route::match(['get', 'post'], 'gestion_clients/ajouter_clients', [ClientsController::class, 'ajouterEnMasse']);
        Route::post('gestion_clients/uploadify', [ClientsController::class, 'upload']);
        Route::match(['get', 'post'], 'gestion_clients/edition_client/{id}', [ClientsController::class, 'edition']);
        Route::get('gestion_clients/supprimer_client/{id}', fn () => redirect('/gestion_clients'));
        Route::post('gestion_clients/supprimer_client/{id}', [ClientsController::class, 'supprimer']);
        Route::match(['get', 'post'], 'gestion_clients/telecharger_clients', [ClientsController::class, 'telecharger']);
        Route::match(['get', 'post'], 'json/SearchClients', [ClientsController::class, 'search']);
    });
});
