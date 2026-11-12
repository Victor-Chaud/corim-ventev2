<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PrivilegeController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'connexion', [LoginController::class, 'index'])->middleware('guest');
Route::get('connexion/deconnection', [LoginController::class, 'logout']);
Route::get('logout', [LoginController::class, 'logout']);

Route::middleware('throttle:20,1')->group(function () {
    Route::match(['get', 'post'], 'json/CheckIfExist', [PasswordController::class, 'checkIfExist']);
    Route::match(['get', 'post'], 'json/CheckQuestion', [PasswordController::class, 'checkQuestion']);
    Route::match(['get', 'post'], 'json/CheckAnswer', [PasswordController::class, 'checkAnswer']);
    Route::match(['get', 'post'], 'json/NewAccountSetting', [PasswordController::class, 'newAccountSetting']);
    Route::match(['get', 'post'], 'connexion/reinitialisation/{id}/{empreinte}', [PasswordController::class, 'reset'])
        ->whereNumber('id')->name('password.reinitialisation');
});
Route::match(['get', 'post'], 'json/GenerateNewAccountSetting/{id?}', fn () => response(''));

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], 'json/ShowToolbar', [PrivilegeController::class, 'showToolbar']);
    Route::match(['get', 'post'], 'json/HideToolbar', [PrivilegeController::class, 'hideToolbar']);

    Route::middleware('admin')->group(function () {
        Route::match(['get', 'post'], 'gestion_privilege', [PrivilegeController::class, 'index']);
        Route::match(['get', 'post'], 'json/UpdateUserPrivilege', [PrivilegeController::class, 'update']);
        Route::match(['get', 'post'], 'json/GetUserPrivilege', [PrivilegeController::class, 'projets']);
        Route::match(['get', 'post'], 'json/GetUserPrivilegeLot', [PrivilegeController::class, 'lots']);
    });
});
