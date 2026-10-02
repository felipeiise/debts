<?php

use App\Http\Middleware\RejectOversizedJson;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [RejectOversizedJson::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            return response()->json(['error' => ['code' => 'validation_failed', 'message' => 'The request is invalid.', 'details' => $exception->errors()]], 422);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => ['code' => 'http_error', 'message' => $exception->getMessage() ?: 'Request failed.']], $exception->getStatusCode());
            }
            return null;
        });
    })->create();
