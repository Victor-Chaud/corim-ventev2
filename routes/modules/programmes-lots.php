<?php

use App\Http\Controllers\ProgrammesLots\GestionProgrammesController;
use App\Http\Controllers\ProgrammesLots\JsonController;
use App\Http\Controllers\ProgrammesLots\LotsController;
use App\Http\Controllers\ProgrammesLots\ProgrammeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'programme', fn () => redirect('/'));
    Route::match(['get', 'post'], 'programme/show/{key}', [ProgrammeController::class, 'show']);
    Route::match(['get', 'post'], 'json/JsonDatagrid/{idprojet}/{iduser?}', [JsonController::class, 'datagrid'])->where('idprojet', '[0-9]+');

    Route::match(['get', 'post'], 'ajouter_lot', fn () => redirect('/'));
    Route::match(['get', 'post'], 'ajouter_lots', fn () => redirect('/'));
    Route::match(['get', 'post'], 'ajouter_plan', fn () => redirect('/'));

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'gestion_programmes', [GestionProgrammesController::class, 'index']);
        Route::match(['get', 'post'], 'gestion_programmes/diaporama_projet/{key}', [GestionProgrammesController::class, 'diaporama']);
        Route::match(['get', 'post'], 'ajouter_programme', [ProgrammeController::class, 'ajouter']);
        Route::match(['get', 'post'], 'modifier_programme', fn () => redirect('/gestion_programmes'));
        Route::match(['get', 'post'], 'modifier_programme/show/{key}', [ProgrammeController::class, 'modifier']);

        Route::match(['get', 'post'], 'gerer_lots', fn () => redirect('/gestion_programmes'));
        Route::match(['get', 'post'], 'gerer_lots/show/{key}', [LotsController::class, 'gerer']);
        Route::match(['get', 'post'], 'gerer_lots/modifier/{key}-{idlot}', [LotsController::class, 'modifier'])->where(['key' => '[^/-]+', 'idlot' => '[0-9]+']);
        Route::match(['get', 'post'], 'ajouter_lot/show/{key}', [LotsController::class, 'ajouter']);
        Route::match(['get', 'post'], 'ajouter_lots/show/{key}', [LotsController::class, 'ajouterEnMasse']);
        Route::get('ajouter_lots/modele', [LotsController::class, 'modele']);
        Route::post('ajouter_lots/uploadify/{key}', [LotsController::class, 'uploadCsv']);
        Route::match(['get', 'post'], 'ajouter_plan/show/{key}', [LotsController::class, 'ajouterPlans']);
        Route::post('ajouter_plan/uploadify/{key}', [LotsController::class, 'uploadPlan']);

        Route::match(['get', 'post'], 'json/DeleteFile', [JsonController::class, 'deleteFile']);
        Route::match(['get', 'post'], 'json/DeleteDiapo', [JsonController::class, 'deleteDiapo']);
        Route::match(['get', 'post'], 'json/ArchivageProjet', [JsonController::class, 'archivage']);
        Route::match(['get', 'post'], 'json/DesarchivageProjet', [JsonController::class, 'desarchivage']);
        Route::match(['get', 'post'], 'json/SupprimerLot', [JsonController::class, 'supprimerLot']);
        Route::match(['get', 'post'], 'json/DeleteAllInProject', [JsonController::class, 'deleteAllInProject']);
    });
});
