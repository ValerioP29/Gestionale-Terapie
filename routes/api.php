<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/whatsapp/queue', [WhatsAppController::class, 'queue']);
