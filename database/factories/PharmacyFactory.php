<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pharmacy>
 */
class PharmacyFactory extends Factory
{
    protected $model = Pharmacy::class;

    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->company());

        return [
            'email' => $this->faker->unique()->safeEmail(),
            'slug_name' => $slug,
            'slug_url' => $slug,
            'phone_number' => $this->faker->numerify('##########'),
            'password' => bcrypt('password'),
            'status_id' => 1,
            'business_name' => $this->faker->company(),
            'nice_name' => $this->faker->companySuffix(),
            'city' => $this->faker->city(),
            'address' => $this->faker->address(),
            'latlng' => null,
            'description' => null,
            'logo' => null,
            'working_info' => null,
            'prompt' => null,
            'img_avatar' => null,
            'img_cover' => null,
            'img_bot' => null,
            'is_deleted' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
            'last_access' => null,
        ];
    }
}
