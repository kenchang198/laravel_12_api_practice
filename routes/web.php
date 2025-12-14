<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// OAuth2.0認証フロー用のログインルート
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'webLogin']);
Route::post('/logout', [AuthController::class, 'webLogout'])->name('logout');

// OAuth2.0認証コード受け取り用のコールバックエンドポイント
Route::get('/callback', [AuthController::class, 'callback'])->name('oauth.callback');
