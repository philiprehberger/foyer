<?php

use App\Http\Responses\ProblemResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'twilio.sig' => \App\Http\Middleware\TwilioSignatureMiddleware::class,
            'internal.hmac' => \App\Http\Middleware\InternalHmacMiddleware::class,
            'internal.loopback' => \App\Http\Middleware\BindToLoopbackMiddleware::class,
            'idempotency' => \App\Http\Middleware\IdempotencyKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! ($request->is('v1/*') || $request->expectsJson())) {
                return null;
            }

            return new ProblemResponse(
                status: 400,
                title: 'Invalid request',
                detail: 'The request body failed validation.',
                errors: $e->errors(),
            );
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! ($request->is('v1/*') || $request->expectsJson())) {
                return null;
            }

            return ProblemResponse::for($e);
        });
    })->create();
