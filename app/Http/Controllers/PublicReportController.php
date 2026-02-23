<?php

namespace App\Http\Controllers;

use App\Models\TherapyReport;
use App\Presenters\TherapyReportPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicReportController extends Controller
{
    public function show(string $token): View
    {
        $report = TherapyReport::query()
            ->where('share_token', $token)
            ->where('valid_until', '>=', now())
            ->firstOrFail();

        $report->loadMissing(['therapy.patient', 'therapy.currentChronicCare', 'therapy.latestSurvey', 'therapy.latestConsent', 'pharmacy']);
        $presenter = new TherapyReportPresenter($report, (array) ($report->content ?? []));

        return view('reports.public-show', [
            'report' => $report,
            'presented' => $presenter->toArray(),
            'pdfUrl' => $report->pdf_path !== null ? Storage::disk('public')->url($report->pdf_path) : null,
        ]);
    }

    public function downloadPdf(string $token): Response
    {
        $report = TherapyReport::query()
            ->where('share_token', $token)
            ->where('valid_until', '>=', now())
            ->firstOrFail();

        abort_if($report->pdf_path === null, 404);

        return response()->file(Storage::disk('public')->path($report->pdf_path));
    }
}
