<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// This is an example route. You can keep it or remove it.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- Authentication Routes ---
Route::prefix('v1/auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout')->middleware('auth:api');
    Route::post('refresh', 'refresh')->middleware('auth:api');
    Route::get('me', 'me')->middleware('auth:api');
    Route::put('update-account', 'updateAccount')->middleware('auth:api');
    Route::delete('delete-account', 'deleteAccount')->middleware('auth:api');
});