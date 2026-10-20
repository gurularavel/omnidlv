<?php

namespace Database\Factories;

use App\Models\Hub;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Hub> */
class HubFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Mərkəzi anbar', 'Nərimanov filialı', 'Yasamal filialı', 'Xətai anbarı']),
            'address' => fake()->streetAddress().', Bakı',
            'lat' => fake()->latitude(40.36, 40.45),
            'lng' => fake()->longitude(49.80, 49.95),
            'tags' => [],
        ];
    }
}
