<?php

use App\Http\Controllers\Workflow\JsonController;
use App\Http\Controllers\Workflow\PagesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'ask_options/{index?}', [PagesController::class, 'askOptions'])->where('index', 'index');
    Route::match(['get', 'post'], 'ask_reservations/{index?}', [PagesController::class, 'askReservations'])->where('index', 'index');
    Route::match(['get', 'post'], 'ask_denonciations/{index?}', [PagesController::class, 'askReferrals'])->where('index', 'index');
    Route::get('options/refusees', [PagesController::class, 'rejectedOptions']);
    Route::get('reservations/refusees', [PagesController::class, 'rejectedReservations']);
    Route::match(['get', 'post'], 'options/refused', [PagesController::class, 'optionsRefused']);
    Route::match(['get', 'post'], 'options/{index?}', [PagesController::class, 'options'])->where('index', 'index');
    Route::match(['get', 'post'], 'reservations/refused', [PagesController::class, 'reservationsRefused']);
    Route::match(['get', 'post'], 'reservations/{index?}', [PagesController::class, 'reservations'])->where('index', 'index');
    Route::match(['get', 'post'], 'ventes/{index?}', [PagesController::class, 'sales'])->where('index', 'index');
    Route::match(['get', 'post'], 'denonciations/{index?}', [PagesController::class, 'referrals'])->where('index', 'index');
    Route::match(['get', 'post'], 'refused_ask/{index?}', [PagesController::class, 'refusedAsk'])->where('index', 'index');

    Route::match(['get', 'post'], 'json/AddOption', [JsonController::class, 'addOption']);
    Route::match(['get', 'post'], 'json/AddReservation', [JsonController::class, 'addReservation']);

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'report/{index?}', [PagesController::class, 'report'])->where('index', 'index');

        Route::match(['get', 'post'], 'json/AcceptAddOption', [JsonController::class, 'acceptAddOption']);
        Route::match(['get', 'post'], 'json/RejetAddOption', [JsonController::class, 'rejectAddOption']);
        Route::match(['get', 'post'], 'json/AcceptAddResa', [JsonController::class, 'acceptAddReservation']);
        Route::match(['get', 'post'], 'json/RejetAddResa', [JsonController::class, 'rejectAddReservation']);
        Route::match(['get', 'post'], 'json/AcceptAddDenonciation', [JsonController::class, 'acceptAddReferral']);
        Route::match(['get', 'post'], 'json/RejetAddDenonciation', [JsonController::class, 'rejectAddReferral']);
        Route::match(['get', 'post'], 'json/PasserEnResa', [JsonController::class, 'convertToReservation']);
        Route::match(['get', 'post'], 'json/AnnulerOption', [JsonController::class, 'cancelOption']);
        Route::match(['get', 'post'], 'json/AnnulerResa', [JsonController::class, 'cancelReservation']);
        Route::match(['get', 'post'], 'json/PasserEnActer', [JsonController::class, 'convertToDeed']);
        Route::match(['get', 'post'], 'json/AnnulerLaVente', [JsonController::class, 'cancelSale']);
        Route::match(['get', 'post'], 'json/DeleteDenonciation', [JsonController::class, 'deleteReferral']);
        Route::match(['get', 'post'], 'json/UpdateDate', [JsonController::class, 'updateDate']);
    });
});
