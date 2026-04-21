<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Rastreador;

class PosicaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rastreador_id' => Rastreador::factory(),
            'data_hora' => fake()->dateTimeBetween('-2 days', 'now'),
            'latitude' => fake()->latitude(-23.0, -15.0),
            'longitude' => fake()->longitude(-50.0, -40.0),
            'velocidade' => fake()->numberBetween(0, 90),
            'angulo' => fake()->numberBetween(0, 360),
            'sinal_gps' => 9,
            'raw_data' => 'raw_simulado_factory',
            'ignorada' => false,
        ];
    }
}
