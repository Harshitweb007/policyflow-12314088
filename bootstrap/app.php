<?php

use App\Exceptions\IllegalStateTransitionException;
use App\Exceptions\IssuanceNotEligibleException;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() || $request->query('debug') === '1',
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->query('debug') === '1' || $request->header('X-Debug') === '1') {
                return response()->json([
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => array_map(fn ($t) => ($t['file'] ?? '').':'.($t['line'] ?? '').' '.($t['function'] ?? '').'()', array_slice($e->getTrace(), 0, 8)),
                ], 500);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Validation failed', 422, $e->errors());
            }

            return null;
        });

        $exceptions->render(function (IllegalStateTransitionException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 409);
            }

            return null;
        });

        $exceptions->render(function (IssuanceNotEligibleException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 409);
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $e->getPrevious();
                if ($previous instanceof ModelNotFoundException || $request->is('api/*')) {
                    return ApiResponse::error('Resource not found', 404);
                }
            }

            return null;
        });
    })->create();

if ($storagePath = env('APP_STORAGE')) {
    $app->useStoragePath($storagePath);
}

return $app;
