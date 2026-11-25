<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua route /api/* selalu dijawab dalam JSON
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Format error seragam dan tanpa stack trace.
        // Error validasi (422) memakai format bawaan Laravel: {"message": ..., "errors": {...}}
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            [$status, $message] = match (true) {
                $e instanceof AuthenticationException   => [401, 'Unauthenticated.'],
                $e instanceof AuthorizationException    => [403, 'This action is unauthorized.'],
                $e instanceof NotFoundHttpException     => [404, 'Resource not found.'],
                $e instanceof HttpExceptionInterface    => [$e->getStatusCode(), $e->getMessage() ?: 'Request failed.'],
                default                                 => [500, 'Server error.'],
            };

            return response()->json(['message' => $message], $status);
        });
    })->create();