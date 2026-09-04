<?php

namespace Database\Factories;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

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
            'name'             => \fake()->name(),
            'email'            => \fake()->unique()->safeEmail(),
            'mobile_number'    => \fake()->numerify('09#########'),
            'complete_address' => \fake()->address(),
            'delivery_area'    => \fake()->city(),
            'password'         => static::$password ??= Hash::make('password'),
            'remember_token'   => Str::random(10),
            'is_admin'         => false,
            'account_status'   => 'good_standing',
        ];
    }
}
