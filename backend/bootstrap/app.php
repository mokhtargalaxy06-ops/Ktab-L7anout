<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetLocale::class);
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*', 'login', 'register', 'logout') || $request->expectsJson());
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            if (! $request->is('api/*', 'login', 'register', 'logout') || ! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
                return $response;
            }

            $status = $response->getStatusCode();
            $code = match ($status) {
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                405 => 'method_not_allowed',
                419 => 'csrf_expired',
                422 => 'validation_failed',
                429 => 'rate_limited',
                default => $status >= 500 ? 'server_error' : 'request_failed',
            };
            $body = ['code' => $code, 'message' => __("api.$code"), 'request_id' => (string) Str::uuid()];

            if ($exception instanceof ValidationException) {
                $body['errors'] = $exception->errors();
            }

            return $response->setData($body);
        });
    })->create();
