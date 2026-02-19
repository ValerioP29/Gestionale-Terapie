<?php

namespace App\Services\Therapies;

use App\Jobs\GenerateReportPdfJob;
use App\Models\Therapy;
use App\Models\TherapyReport;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateTherapyReportService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Therapy $therapy): TherapyReport
    {
        return DB::transaction(function () use ($therapy): TherapyReport {
            $therapy->loadMissing([
                'patient',
                'chronicCare',
                'conditionSurveys',
                'followups',
                'reminders',
            ]);

            $report = TherapyReport::query()->create([
                'pharmacy_id' => $therapy->pharmacy_id,
                'therapy_id' => $therapy->id,
                'share_token' => $this->generateUniqueShareToken(),
                'valid_until' => Carbon::now()->addDays(30),
                'content' => [
                    'patient' => $therapy->patient?->toArray(),
                    'therapy' => $therapy->toArray(),
                    'chronicCare' => $therapy->chronicCare->map->toArray()->all(),
                    'survey' => $therapy->conditionSurveys->map->toArray()->all(),
                    'followups' => $therapy->followups->map->toArray()->all(),
                    'reminders' => $therapy->reminders->map->toArray()->all(),
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

            $this->auditLogger->log(
                pharmacyId: $therapy->pharmacy_id,
                action: 'generate_report',
                subject: $report,
                meta: [
                    'therapy_id' => $therapy->id,
                    'valid_until' => $report->valid_until?->toIso8601String(),
                ],
            );

            GenerateReportPdfJob::dispatch($report->id);

            return $report;
        });
    }

    private function generateUniqueShareToken(): string
    {
        do {
            $token = Str::random(40);
        } while (TherapyReport::query()->where('share_token', $token)->exists());

        return $token;
    }
}
