<?php

use App\Http\Controllers\PublicReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reports/{token}', [PublicReportController::class, 'show'])->name('reports.public');
Route::get('/reports/{token}/pdf', [PublicReportController::class, 'downloadPdf'])->name('reports.pdf');
