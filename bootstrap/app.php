<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Exceptions\CurrentPharmacyNotResolvedException;
use App\Http\Middleware\ResolveCurrentPharmacy;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        App\Console\Commands\DispatchDueRemindersCommand::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', ResolveCurrentPharmacy::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (CurrentPharmacyNotResolvedException $exception, Request $request) {
            $message = 'Farmacia corrente non risolta. Seleziona una farmacia e riprova.';

            if ($request->is('livewire/*')) {
                return response()->json([
                    'message' => $message,
                    'error' => 'current_pharmacy_not_resolved',
                ], 422);
            }

            return redirect('/admin')->with('current_pharmacy_error', $message);
        });
    })->create();
