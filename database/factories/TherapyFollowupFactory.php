<?php

namespace Database\Factories;

use App\Models\Therapy;
use App\Models\TherapyFollowup;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapyFollowup>
 */
class TherapyFollowupFactory extends Factory
{
    protected $model = TherapyFollowup::class;

    public function definition(): array
    {
        $therapy = Therapy::factory()->create();
        app(CurrentPharmacy::class)->setId($therapy->pharmacy_id);

        return [
            'therapy_id' => $therapy->id,
            'pharmacy_id' => $therapy->pharmacy_id,
            'created_by' => null,
            'entry_type' => 'followup',
            'check_type' => null,
            'occurred_at' => now(),
            'risk_score' => null,
            'pharmacist_notes' => null,
            'education_notes' => null,
            'snapshot' => [],
            'follow_up_date' => now()->toDateString(),
            'canceled_at' => null,
        ];
    }
}
