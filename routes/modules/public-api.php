<?php

use App\Http\Controllers\PublicApi\FirstsearchController;
use App\Http\Controllers\PublicApi\SearchController;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware('throttle:600,1')
    ->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, SecurityHeaders::class])
    ->group(function () {
        foreach (['', 'index.php/'] as $prefix) {
            Route::any($prefix.'firstsearch/{index?}', FirstsearchController::class)->where('index', 'index');
            Route::any($prefix.'search/news', [SearchController::class, 'news']);
            Route::any($prefix.'search/{index?}', [SearchController::class, 'index'])->where('index', 'index');
        }
    });
