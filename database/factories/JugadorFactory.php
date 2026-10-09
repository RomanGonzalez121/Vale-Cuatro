<?php

namespace Database\Factories;

use App\Models\Jugador;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Jugador>
 */
class JugadorFactory extends Factory
{
    /**
     * La contraseña se calcula una sola vez para todos los jugadores de prueba.
     */
    protected static ?string $password;

    /**
     * Un jugador con cuenta.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'apodo' => 'Jugador '.fake()->unique()->numberBetween(1000, 999999),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Un invitado: sin email ni contraseña.
     */
    public function invitado(): static
    {
        return $this->state(fn (array $atributos) => [
            'apodo' => 'Invitado '.fake()->unique()->numberBetween(10000, 99999),
            'email' => null,
            'password' => null,
        ]);
    }

    /**
     * Un jugador de ejemplo del ranking: sin email ni contraseña, y marcado.
     */
    public function deEjemplo(): static
    {
        return $this->state(fn (array $atributos) => [
            'apodo' => 'Ejemplo '.fake()->unique()->numberBetween(10000, 99999),
            'email' => null,
            'password' => null,
            'de_ejemplo' => true,
        ]);
    }
}
