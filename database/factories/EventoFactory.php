<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Rastreador;

class EventoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rastreador_id' => Rastreador::factory(),
            'posicao_id' => null,
            'tipo' => fake()->randomElement(['IGNICAO_ON', 'IGNICAO_OFF', 'SOS', 'BATERIA_BAIXA']),
            'descricao' => 'Evento gerado automaticamente',
            'codigo_raw' => 'SIMULADO',
        ];
    }
}
