<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'surname' => fake()->lastName(),
            'name' => fake()->firstName(),
            'patronymic' => fake()->firstName().'ович', // упрощённое отчество
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::query()->inRandomOrder()->value('id') ?? Role::query()->updateOrCreate(
                ['id' => 3],
                ['name' => 'Бухгалтер']
            )->id,
        ];
    }
}
