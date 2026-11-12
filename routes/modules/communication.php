<?php

use App\Http\Controllers\Communication\ContactController;
use App\Http\Controllers\Communication\EmailingController;
use App\Http\Controllers\Communication\FlashInfoController;
use App\Http\Controllers\Communication\MessageController;
use App\Http\Controllers\Communication\MessagerieController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'messagerie/{index?}', [MessagerieController::class, 'index'])->where('index', 'index');
    Route::match(['get', 'post'], 'messagerie/consulter/{cle}', [MessagerieController::class, 'consulter']);
    Route::get('messagerie/supprimer', fn () => redirect('/messagerie'));
    Route::post('messagerie/supprimer', [MessagerieController::class, 'supprimer']);
    Route::get('messagerie/supprimer_tout', fn () => redirect('/messagerie'));
    Route::post('messagerie/supprimer_tout', [MessagerieController::class, 'supprimerTout']);

    Route::match(['get', 'post'], 'message/{index?}', fn () => redirect('/'))->where('index', 'index');
    Route::match(['get', 'post'], 'message/repondre/{cle}', [MessageController::class, 'repondre']);

    Route::match(['get', 'post'], 'contact/{index?}', [ContactController::class, 'index'])->where('index', 'index');

    Route::match(['get', 'post'], 'agenda/{any?}', fn () => redirect('/'))->where('any', '.*');

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'message/envoyer', [MessageController::class, 'envoyer']);

        Route::match(['get', 'post'], 'emailing/{index?}', [EmailingController::class, 'index'])->where('index', 'index');
        Route::match(['get', 'post'], 'json/SearchClientEmailing', [EmailingController::class, 'search']);
        Route::match(['get', 'post'], 'emails/{index?}', [EmailingController::class, 'emails'])->where('index', 'index');

        Route::match(['get', 'post'], 'flash_info/{index?}', [FlashInfoController::class, 'index'])->where('index', 'index');
        Route::match(['get', 'post'], 'flash_info/add', [FlashInfoController::class, 'add']);
        Route::match(['get', 'post'], 'flash_info/edit/{id}', [FlashInfoController::class, 'edit']);
        Route::get('flash_info/delete/{id}', fn () => redirect('/flash_info'));
        Route::post('flash_info/delete/{id}', [FlashInfoController::class, 'delete']);
    });
});
