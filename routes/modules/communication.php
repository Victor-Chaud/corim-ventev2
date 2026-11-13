<?php

use App\Http\Controllers\Communication\ContactController;
use App\Http\Controllers\Communication\DocumentationRequestsController;
use App\Http\Controllers\Communication\FlashInfoController;
use App\Http\Controllers\Communication\MessageController;
use App\Http\Controllers\Communication\MessagingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'messagerie/{index?}', [MessagingController::class, 'index'])->where('index', 'index');
    Route::match(['get', 'post'], 'messagerie/consulter/{cle}', [MessagingController::class, 'read']);
    Route::get('messagerie/supprimer', fn () => redirect('/messagerie'));
    Route::post('messagerie/supprimer', [MessagingController::class, 'delete']);
    Route::get('messagerie/supprimer_tout', fn () => redirect('/messagerie'));
    Route::post('messagerie/supprimer_tout', [MessagingController::class, 'deleteAll']);

    Route::match(['get', 'post'], 'message/{index?}', fn () => redirect('/'))->where('index', 'index');
    Route::match(['get', 'post'], 'message/repondre/{cle}', [MessageController::class, 'reply']);

    Route::match(['get', 'post'], 'contact/{index?}', [ContactController::class, 'index'])->where('index', 'index');

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'message/envoyer', [MessageController::class, 'send']);

        Route::match(['get', 'post'], 'emails/{index?}', [DocumentationRequestsController::class, 'index'])->where('index', 'index');

        Route::match(['get', 'post'], 'flash_info/{index?}', [FlashInfoController::class, 'index'])->where('index', 'index');
        Route::match(['get', 'post'], 'flash_info/add', [FlashInfoController::class, 'add']);
        Route::match(['get', 'post'], 'flash_info/edit/{id}', [FlashInfoController::class, 'edit']);
        Route::get('flash_info/delete/{id}', fn () => redirect('/flash_info'));
        Route::post('flash_info/delete/{id}', [FlashInfoController::class, 'delete']);
    });
});
