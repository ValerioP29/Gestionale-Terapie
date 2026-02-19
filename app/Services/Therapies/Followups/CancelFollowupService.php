<?php

namespace App\Services\Therapies\Followups;

use App\Models\Therapy;
use App\Models\TherapyFollowup;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CancelFollowupService
{
    public function handle(Therapy $therapy, TherapyFollowup $followup): TherapyFollowup
    {
        if ($followup->therapy_id !== $therapy->id || $followup->pharmacy_id !== $therapy->pharmacy_id) {
            throw ValidationException::withMessages([
                'followup_id' => 'Followup non valido per la terapia selezionata.',
            ]);
        }

        if ($followup->canceled_at === null) {
            $followup->forceFill([
                'canceled_at' => CarbonImmutable::now(),
            ])->save();
        }

        return $followup->fresh();
    }
}
