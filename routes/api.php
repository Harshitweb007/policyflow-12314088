<?php

use App\Http\Controllers\Api\MockInsurerCallbackController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests/{policyRequest}', [PolicyRequestController::class, 'show']);
Route::post('/policy-requests/{policyRequest}/submit', [PolicyRequestController::class, 'submit']);
Route::post('/reconcile', [PolicyRequestController::class, 'reconcile']);
Route::post('/mock-insurer/callback', MockInsurerCallbackController::class);
