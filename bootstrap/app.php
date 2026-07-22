<?php

use App\Http\Middleware\ApiTokenAuthenticate;
use App\Http\Middleware\KmiAuthenticate;
use App\Http\Middleware\KmiGuest;
use App\Http\Middleware\KmiMentorOnly;
use App\Http\Middleware\KmiRoleAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token' => ApiTokenAuthenticate::class,
            'kmi.auth' => KmiAuthenticate::class,
            'kmi.guest' => KmiGuest::class,
            'kmi.mentor' => KmiMentorOnly::class,
            'kmi.access' => KmiRoleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $exception->errors(),
            ], 422);
        });
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Data yang diminta tidak ditemukan.',
            ], 404);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
            ], $exception->getStatusCode());
        });
    })->create();
