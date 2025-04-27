<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoanController;

Route::post('/loan/create', [LoanController::class, 'create']);
Route::post('/loans/return/{id}', [LoanController::class, 'returnBook']);
