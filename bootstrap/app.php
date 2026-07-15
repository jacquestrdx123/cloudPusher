<?php

use App\Http\Middleware\AuthenticateCompanyOrUserToken;
use App\Http\Middleware\AuthenticateCompanyToken;
use App\Http\Middleware\AuthenticateUserToken;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Models\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'webhook.signature' => VerifyWebhookSignature::class,
            'company.token' => AuthenticateCompanyToken::class,
            'company.or.user.token' => AuthenticateCompanyOrUserToken::class,
            'user.token' => AuthenticateUserToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $previous = $exception->getPrevious();

            if (! $previous instanceof ModelNotFoundException || $previous->getModel() !== Company::class) {
                return null;
            }

            $slug = collect($previous->getIds())->first();

            return response()->json([
                'message' => is_string($slug) && $slug !== ''
                    ? "No company found with slug \"{$slug}\"."
                    : 'Company not found.',
            ], 404);
        });
    })->create();
