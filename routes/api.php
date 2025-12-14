<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaginationController;

Route::get('/users/offset', [PaginationController::class, 'offset']);
Route::get('/users/cursor', [PaginationController::class, 'cursor']);

