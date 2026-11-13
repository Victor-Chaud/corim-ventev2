<?php

use App\Http\Controllers\ClientsProspects\ProspectsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'json/SearchProspect', [ProspectsController::class, 'search']);
    Route::match(['get', 'post'], 'json/AddProspect', [ProspectsController::class, 'add']);

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'gestion_prospects/{index?}', fn () => redirect('/denonciations'))->where('index', 'index');
    });
});
