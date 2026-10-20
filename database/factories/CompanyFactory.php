<?php

namespace Database\Factories;

use App\Models\Company;
use App\Support\Plans;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'short_name' => Str::limit($name, 20, ''),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'timezone' => 'Asia/Baku',
            'locale' => 'az',
            'country' => 'AZ',
            'currency' => 'AZN',
            'plan_slug' => 'scale',
            'trial_ends_at' => now()->addDays(Plans::TRIAL_DAYS),
            'settings' => [],
        ];
    }

    public function expired(): static
    {
        return $this->state(['trial_ends_at' => now()->subDay(), 'subscription_ends_at' => null]);
    }
}
