<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rastreador;
use App\Models\Posicao;
use Laravel\Sanctum\Sanctum;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_access_telemetry_without_auth()
    {
        $response = $this->getJson('/api/v1/telemetria/1234567890/ultimos');
        $response->assertStatus(401);
    }

    public function test_can_get_latest_records_with_limit()
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
        
        $rastreador = Rastreador::factory()->create(['imei' => '1234567890']);
        Posicao::factory(10)->create(['rastreador_id' => $rastreador->id]);

        $response = $this->getJson('/api/v1/telemetria/1234567890/ultimos?qtde_registros=5');
        
        $response->assertStatus(200)
                 ->assertJsonPath('sucesso', true)
                 ->assertJsonCount(5, 'registros');
    }

    public function test_can_get_history_with_date_filters()
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
        
        $rastreador = Rastreador::factory()->create(['imei' => '0987654321']);
        
        Posicao::factory()->create([
            'rastreador_id' => $rastreador->id,
            'data_hora' => '2026-04-10 10:00:00'
        ]);

        $response = $this->getJson('/api/v1/telemetria/0987654321/historico?data_inicio=2026-04-09 00:00:00&data_fim=2026-04-11 23:59:59');
        
        $response->assertStatus(200)->assertJsonPath('sucesso', true);
    }
}
