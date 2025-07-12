<?php

use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleAppearance;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $missingVoucherParam = collect($request->route()?->parametersWithoutNulls())->doesntContain('voucher');
            $expectsVoucher = collect($request->route()?->parameterNames() ?? [])->contains('voucher');

            if ($expectsVoucher && $missingVoucherParam) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'Cash code not found.',
                ]);
            }

            return null;
        });
//        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
//            $previous = $e->getPrevious();
//
//            // Check if the model not found is a Voucher
//            if (
//                $previous instanceof ModelNotFoundException &&
//                $previous->getModel() === Voucher::class &&
//                $request->routeIs('redeem.wallet')
//            ) {
//                // Return back with a ValidationException
//                throw ValidationException::withMessages([
//                    'voucher_code' => 'Voucher code not found.',
//                ]);
//            }
//
//            return null;
//        });
    })
    ->create();
