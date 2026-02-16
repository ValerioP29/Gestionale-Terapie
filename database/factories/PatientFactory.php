<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $pharmacy = Pharmacy::factory()->create();
        app(CurrentPharmacy::class)->setId($pharmacy->id);

        return [
            'pharmacy_id' => $pharmacy->id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'birth_date' => $this->faker->date(),
            'codice_fiscale' => strtoupper($this->faker->bothify('??????##?##?###?')),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'notes' => null,
        ];
    }
}
