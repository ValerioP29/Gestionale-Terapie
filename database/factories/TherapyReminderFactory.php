<?php

namespace Database\Factories;

use App\Models\Therapy;
use App\Models\TherapyReminder;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapyReminder>
 */
class TherapyReminderFactory extends Factory
{
    protected $model = TherapyReminder::class;

    public function definition(): array
    {
        $therapy = Therapy::factory()->create();
        app(CurrentPharmacy::class)->setId($therapy->pharmacy_id);

        return [
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'title' => $this->faker->sentence(3),
            'frequency' => 'weekly',
            'weekday' => null,
            'first_due_at' => now(),
            'next_due_at' => now()->addDay(),
            'last_done_at' => null,
            'status' => 'active',
        ];
    }
}
