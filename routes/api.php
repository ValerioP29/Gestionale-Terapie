<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Middleware\ResolveCurrentPharmacy;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(ResolveCurrentPharmacy::class)->group(function (): void {
    Route::post('/whatsapp/queue', [WhatsAppController::class, 'queue']);
});
