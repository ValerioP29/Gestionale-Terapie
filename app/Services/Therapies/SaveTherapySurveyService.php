<?php

namespace App\Services\Therapies;

use App\Models\Therapy;
use Carbon\Carbon;

class SaveTherapySurveyService
{
    public function handle(Therapy $therapy, ?array $survey): void
    {
        if ($survey === null || $survey === []) {
            return;
        }

        $therapy->conditionSurveys()->create([
            'condition_type' => $survey['condition_type'],
            'level' => $survey['level'],
            'answers' => $survey['answers'] ?? null,
            'compiled_at' => Carbon::now(),
        ]);
    }
}
