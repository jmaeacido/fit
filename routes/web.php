<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShirtController;

Route::get('/', [ShirtController::class, 'page']);
Route::get('/login', [ShirtController::class, 'page'])->name('login');
Route::post('/login', [ShirtController::class, 'login'])->middleware('throttle:5,1');
Route::post('/submissions/save', [ShirtController::class, 'save'])->middleware('throttle:20,1');
Route::post('/submissions', [ShirtController::class, 'store'])->middleware('throttle:20,1');
Route::middleware('auth')->group(function () {
    Route::get('/admin', [ShirtController::class, 'page']);
    Route::get('/admin/submissions', [ShirtController::class, 'index']);
    Route::get('/admin/export', [ShirtController::class, 'export']);
    Route::post('/logout', [ShirtController::class, 'logout']);
});
