<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Rastreador;
use App\Models\Posicao;
use App\Models\Evento;

class TelemetrySeeder extends Seeder
{
    public function run(): void
    {
        // Cria usuário da API para o Swagger
        User::firstOrCreate(
            ['email' => 'api@rastreador.com'],
            [
                'name' => 'Swagger Test User',
                'password' => Hash::make('password'),
            ]
        );

        // Cria 5 Rastreadores diferentes
        $rastreadores = Rastreador::factory(5)->create();

        // Para cada rastreador, gera posições e eventos
        foreach ($rastreadores as $rastreador) {
            // Cria 50 posições recentes para o rastreador
            Posicao::factory(50)->create([
                'rastreador_id' => $rastreador->id,
            ]);

            // Cria 10 eventos
            Evento::factory(10)->create([
                'rastreador_id' => $rastreador->id,
            ]);
            
            // Força a última posição na data atual para garantir no mapa também
            Posicao::factory()->create([
                'rastreador_id' => $rastreador->id,
                'data_hora' => now(),
                'velocidade' => 65,
                'latitude' => -15.7801,
                'longitude' => -47.9292,
            ]);
        }
    }
}
