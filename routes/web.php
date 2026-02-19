<?php

use App\Http\Controllers\PublicReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Redirect root to /admin
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/admin', 302);

/*
|--------------------------------------------------------------------------
| Public Reports
|--------------------------------------------------------------------------
*/

Route::get('/reports/{token}', [PublicReportController::class, 'show'])
    ->name('reports.public');

Route::get('/reports/{token}/pdf', [PublicReportController::class, 'downloadPdf'])
    ->name('reports.pdf');
