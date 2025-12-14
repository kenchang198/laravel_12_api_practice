<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaginationController;

// 認証不要のエンドポイント
Route::post('/login', [AuthController::class, 'login']);

// OAuth2.0認証エンドポイント（Passportが自動的に提供）
// GET /oauth/authorize - 認証コード発行
// POST /oauth/token - アクセストークン発行
// POST /oauth/token/refresh - リフレッシュトークン

// 認証が必要なエンドポイント
Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// @docs/performance/pagination.md
Route::get('/users/offset', [PaginationController::class, 'offset']);
Route::get('/users/cursor', [PaginationController::class, 'cursor']);

