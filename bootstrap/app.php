<?php

use App\Http\ApiError;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureUserRole;
use App\Services\OxaPay\OxaPayException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'oxapay/webhook',
        ]);

        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeRoute());

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'api.key' => AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The merchant API always answers in its own JSON error format.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (ValidationException $e, Request $request) {
            return $request->is('api/*') ? ApiError::validation($e) : null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->is('api/*') ? ApiError::json(404, 'not_found', 'Resource not found.') : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return $request->is('api/*') ? ApiError::json(429, 'rate_limited', 'Too many requests. Slow down and retry shortly.') : null;
        });

        $exceptions->render(function (OxaPayException $e, Request $request) {
            return $request->is('api/*') ? ApiError::json(502, 'gateway_error', 'The payment network could not process the request: '.$e->getMessage()) : null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            return $request->is('api/*') ? ApiError::json($e->getStatusCode(), 'http_error', $e->getMessage() ?: 'Request failed.') : null;
        });
    })->create();
