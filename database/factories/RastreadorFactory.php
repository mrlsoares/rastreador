<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RastreadorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'imei' => fake()->unique()->numerify('86802203#######'),
            'nome' => 'Caminhão ' . fake()->word(),
            'placa' => fake()->bothify('???-####'),
            'modelo_veiculo' => fake()->randomElement(['Volvo FH', 'Scania', 'Mercedes-Benz Actros']),
            'descricao' => 'Simulado para Swagger',
            'ativo' => true,
            'ignicao' => fake()->boolean(80), 
            'em_panico' => fake()->boolean(5), 
            'ultimo_contato' => now(),
        ];
    }
}
