<?php

use App\Http\Controllers\ProgramsLots\JsonController;
use App\Http\Controllers\ProgramsLots\LotsController;
use App\Http\Controllers\ProgramsLots\ProgramController;
use App\Http\Controllers\ProgramsLots\ProgramManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'programme', fn () => redirect('/'));
    Route::match(['get', 'post'], 'programme/show/{key}', [ProgramController::class, 'show']);
    Route::match(['get', 'post'], 'json/JsonDatagrid/{idprojet}/{iduser?}', [JsonController::class, 'datagrid'])->where('idprojet', '[0-9]+');

    Route::match(['get', 'post'], 'ajouter_lot', fn () => redirect('/'));
    Route::match(['get', 'post'], 'ajouter_lots', fn () => redirect('/'));
    Route::match(['get', 'post'], 'ajouter_plan', fn () => redirect('/'));

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'gestion_programmes', [ProgramManagementController::class, 'index']);
        Route::match(['get', 'post'], 'gestion_programmes/diaporama_projet/{key}', [ProgramManagementController::class, 'slideshow']);
        Route::match(['get', 'post'], 'ajouter_programme', [ProgramController::class, 'add']);
        Route::match(['get', 'post'], 'modifier_programme', fn () => redirect('/gestion_programmes'));
        Route::match(['get', 'post'], 'modifier_programme/show/{key}', [ProgramController::class, 'edit']);

        Route::match(['get', 'post'], 'gerer_lots', fn () => redirect('/gestion_programmes'));
        Route::match(['get', 'post'], 'gerer_lots/show/{key}', [LotsController::class, 'manage']);
        Route::match(['get', 'post'], 'gerer_lots/modifier/{key}-{idlot}', [LotsController::class, 'edit'])->where(['key' => '[^/-]+', 'idlot' => '[0-9]+']);
        Route::match(['get', 'post'], 'ajouter_lot/show/{key}', [LotsController::class, 'add']);
        Route::match(['get', 'post'], 'ajouter_lots/show/{key}', [LotsController::class, 'addBulk']);
        Route::get('ajouter_lots/modele', [LotsController::class, 'template']);
        Route::post('ajouter_lots/uploadify/{key}', [LotsController::class, 'uploadCsv']);
        Route::match(['get', 'post'], 'ajouter_plan/show/{key}', [LotsController::class, 'addPlans']);
        Route::post('ajouter_plan/uploadify/{key}', [LotsController::class, 'uploadPlan']);

        Route::match(['get', 'post'], 'json/DeleteFile', [JsonController::class, 'deleteFile']);
        Route::match(['get', 'post'], 'json/DeleteDiapo', [JsonController::class, 'deleteSlide']);
        Route::match(['get', 'post'], 'json/ArchivageProjet', [JsonController::class, 'archive']);
        Route::match(['get', 'post'], 'json/DesarchivageProjet', [JsonController::class, 'unarchive']);
        Route::match(['get', 'post'], 'json/SupprimerLot', [JsonController::class, 'deleteLot']);
        Route::match(['get', 'post'], 'json/DeleteAllInProject', [JsonController::class, 'deleteAllInProject']);
    });
});
