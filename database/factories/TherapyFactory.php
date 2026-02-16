<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Therapy>
 */
class TherapyFactory extends Factory
{
    protected $model = Therapy::class;

    public function definition(): array
    {
        $pharmacy = Pharmacy::factory()->create();
        app(CurrentPharmacy::class)->setId($pharmacy->id);

        $patient = Patient::factory()->create([
            'pharmacy_id' => $pharmacy->id,
        ]);

        return [
            'pharmacy_id' => $pharmacy->id,
            'patient_id' => $patient->id,
            'therapy_title' => $this->faker->sentence(3),
            'therapy_description' => $this->faker->optional()->sentence(),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => null,
        ];
    }
}
