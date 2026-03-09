<?php

namespace App\Jobs;

use App\Models\TherapyReport;
use App\Presenters\TherapyReportPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $reportId)
    {
    }

    public function handle(): void
    {
        $report = TherapyReport::query()->find($this->reportId);

        if ($report === null) {
            return;
        }

        $report->forceFill([
            'status' => TherapyReport::STATUS_PROCESSING,
            'error_message' => null,
            'failed_at' => null,
        ])->save();

        try {
            $report->loadMissing(['therapy.patient', 'therapy.currentChronicCare', 'therapy.latestSurvey', 'therapy.latestConsent', 'pharmacy']);

            $presenter = new TherapyReportPresenter($report, (array) ($report->content ?? []));

            $pdf = Pdf::loadView('reports.pdf', [
                'report' => $report,
                'presented' => $presenter->toArray(),
                'generatedAtRome' => CarbonImmutable::now('Europe/Rome')->format('d/m/Y H:i'),
                'validUntilRome' => $report->valid_until?->setTimezone('Europe/Rome')->format('d/m/Y H:i'),
            ]);

            $path = sprintf('reports/%s/report-%d.pdf', $report->share_token, $report->id);
            Storage::disk('public')->put($path, $pdf->output());

            $report->forceFill([
                'pdf_path' => $path,
                'pdf_generated_at' => now(),
                'status' => TherapyReport::STATUS_COMPLETED,
                'error_message' => null,
                'failed_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            $report->forceFill([
                'status' => TherapyReport::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'failed_at' => now(),
            ])->save();

            throw $exception;
        }
    }
}
