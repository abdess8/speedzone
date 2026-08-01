<?php

namespace Database\Factories;

use App\Enums\AlertFormat;
use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'message' => '<p>'.$this->faker->sentence(12).'</p>',
            'type' => AlertType::INFO->value,
            'display_format' => AlertFormat::BANNER->value,
            'is_dismissible' => true,
            'target_roles' => [Alert::EVERYONE],
            'target_cities' => [Alert::EVERYONE],
            'target_user_ids' => [],
            'end_date' => now()->addWeek(),
            'is_active' => true,
        ];
    }

    public function modal(): static
    {
        return $this->state(['display_format' => AlertFormat::MODAL->value]);
    }

    public function permanent(): static
    {
        return $this->state([
            'display_format' => AlertFormat::BANNER->value,
            'is_dismissible' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(['end_date' => now()->subDay()]);
    }

    public function disabled(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function forRoles(array $roles): static
    {
        return $this->state(['target_roles' => $roles]);
    }

    /**
     * @param  array<int, int|string>  $cityIds
     */
    public function forCities(array $cityIds): static
    {
        return $this->state(['target_cities' => $cityIds]);
    }

    /**
     * An alert addressed to named people only: both broadcast dimensions are
     * emptied, which is how the model expresses "nobody by role or city".
     *
     * @param  array<int, int>  $userIds
     */
    public function forUsers(array $userIds): static
    {
        return $this->state([
            'target_roles' => [],
            'target_cities' => [],
            'target_user_ids' => $userIds,
        ]);
    }
}
