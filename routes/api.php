<?php

use App\Http\Controllers\Api\MockInsurerCallbackController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests/{policyRequest}', [PolicyRequestController::class, 'show']);
Route::post('/policy-requests/{policyRequest}/submit', [PolicyRequestController::class, 'submit']);
Route::post('/reconcile', [PolicyRequestController::class, 'reconcile']);
Route::post('/mock-insurer/callback', MockInsurerCallbackController::class);

Route::match(['GET', 'POST'], '/system/migrate', function (Request $request) {
    $secret = $request->input('secret') ?? $request->header('X-Deploy-Secret');
    $expected = config('app.key') ?: env('DEPLOY_SECRET');

    if (! $expected || ! hash_equals((string) $expected, (string) $secret)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Provide valid secret matching APP_KEY or DEPLOY_SECRET.',
        ], 403);
    }

    Artisan::call('migrate', ['--force' => true]);
    $output = Artisan::output();

    return response()->json([
        'success' => true,
        'message' => 'Database migration executed successfully.',
        'output' => trim($output),
    ]);
});
