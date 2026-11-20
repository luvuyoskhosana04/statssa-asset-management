<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'code' => $this->faker->unique()->bothify('PROJ-####-????'),
            'start_date' => today()->subDay(),
            'end_date' => today()->addMonth(),
            'status' => 'active',
        ];
    }
}
