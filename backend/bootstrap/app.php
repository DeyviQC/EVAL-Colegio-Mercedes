<?php

use App\Http\Middleware\CompressJson;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['name']);
        $middleware->prepend(CompressJson::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $error, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['category' => 'unauthenticated', 'message' => 'Inicia sesión para continuar.'], 401);
            }
        });
        $exceptions->render(function (ValidationException $error, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['category' => 'validation', 'message' => 'Revisa los campos del formulario.', 'errors' => $error->errors()], 422);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $error, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['category' => match ($error->getStatusCode()) {
                    403 => 'forbidden', 404 => 'not_found', 419 => 'session_expired', 429 => 'rate_limited', default => 'http_error'
                }, 'message' => match ($error->getStatusCode()) {
                    403 => 'Tu cuenta no tiene acceso a este registro.', 404 => 'Registro no encontrado.', 419 => 'Tu sesión venció. Inicia sesión nuevamente.', 429 => 'Demasiados intentos. Espera antes de continuar.', default => 'No se pudo completar la solicitud.'
                }], $error->getStatusCode());
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
