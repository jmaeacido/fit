<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShirtController;

Route::get('/', [ShirtController::class, 'page']);
Route::get('/admin', [ShirtController::class, 'page']);
Route::get('/admin/submissions', [ShirtController::class, 'index']);
Route::post('/admin/submissions/{submission}', [ShirtController::class, 'update']);
Route::get('/admin/export', [ShirtController::class, 'export']);
Route::redirect('/login', '/admin');
Route::post('/submissions/save', [ShirtController::class, 'save'])->middleware('throttle:20,1');
Route::post('/submissions', [ShirtController::class, 'store'])->middleware('throttle:20,1');
