<?php

namespace App\Jobs;

use App\Models\TherapyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'content' => $report->content ?? [],
            'generatedAtRome' => CarbonImmutable::now('Europe/Rome')->format('d/m/Y H:i'),
            'validUntilRome' => $report->valid_until?->setTimezone('Europe/Rome')->format('d/m/Y H:i'),
        ]);

        $path = sprintf('reports/%s/report-%d.pdf', $report->share_token, $report->id);
        Storage::disk('public')->put($path, $pdf->output());

        $report->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->save();
    }
}
