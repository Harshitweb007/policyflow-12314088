<?php

use App\Http\Controllers\Web\PolicyDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PolicyDashboardController::class, 'index'])->name('dashboard');
Route::get('/intake', [PolicyDashboardController::class, 'intake'])->name('intake');
Route::get('/requests', [PolicyDashboardController::class, 'list'])->name('requests.index');
Route::get('/requests/{policyRequest}', [PolicyDashboardController::class, 'show'])->name('requests.show');
