<?php

use App\Http\Controllers\Public\PagesController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'app/{index?}', [PagesController::class, 'app'])->where('index', 'index');

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/', [PagesController::class, 'welcome']);
    Route::match(['get', 'post'], 'welcome/{index?}', [PagesController::class, 'welcome'])->where('index', 'index');
    Route::match(['get', 'post'], 'cgu/{index?}', [PagesController::class, 'cgu'])->where('index', 'index');
    Route::match(['get', 'post'], 'mentions_legales/{index?}', [PagesController::class, 'mentions'])->where('index', 'index');
    Route::match(['get', 'post'], 'rechercher/{action?}', [PagesController::class, 'search'])->where('action', 'index|par_residence|par_lot');
});
