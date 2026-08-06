<?php

namespace Tests\Feature;

use App\Models\Esp32Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    // --- Leitura exige autenticação (Fase 1) ---

    public function test_fleet_requires_auth(): void
    {
        $this->getJson('/api/v1/esp32/fleet')->assertStatus(401);
    }

    public function test_rastreadores_index_requires_auth(): void
    {
        $this->getJson('/api/v1/rastreadores')->assertStatus(401);
    }

    public function test_delete_dispositivo_requires_auth(): void
    {
        $this->deleteJson('/api/v1/esp32/dispositivos/AA:BB:CC')->assertStatus(401);
    }

    public function test_authenticated_user_reads_fleet(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson('/api/v1/esp32/fleet')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // --- Ingestão ESP32 exige token por dispositivo (X-DEVICE-TOKEN) ---

    public function test_ingest_rejects_without_device_token(): void
    {
        $this->postJson('/api/v1/esp32/telemetry', ['lat' => -23.5])
            ->assertStatus(401);
    }

    public function test_ingest_rejects_wrong_device_token(): void
    {
        Esp32Dispositivo::create(['empresa_id' => 1, 'identificador' => 'AA:BB', 'ativo' => true])
            ->gerarTokenApi();

        $this->withHeader('X-DEVICE-TOKEN', 'token-invalido')
            ->postJson('/api/v1/esp32/telemetry', ['lat' => -23.5])
            ->assertStatus(401);
    }

    public function test_ingest_rejects_inactive_device(): void
    {
        $device = Esp32Dispositivo::create(['empresa_id' => 1, 'identificador' => 'AA:BB', 'ativo' => false]);
        $token = $device->gerarTokenApi();

        $this->withHeader('X-DEVICE-TOKEN', $token)
            ->postJson('/api/v1/esp32/telemetry', ['lat' => -23.5])
            ->assertStatus(401);
    }

    public function test_ingest_accepts_valid_device_token_and_attributes_empresa(): void
    {
        $device = Esp32Dispositivo::create(['empresa_id' => 2, 'identificador' => 'AA:BB:CC', 'ativo' => true]);
        $token = $device->gerarTokenApi();

        $this->withHeader('X-DEVICE-TOKEN', $token)
            ->postJson('/api/v1/esp32/telemetry', ['lat' => -23.5, 'lon' => -46.6])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        // Telemetria fica no dispositivo (empresa 2) do token, sem chutar empresa padrão.
        $this->assertDatabaseHas('esp32_telemetrias', [
            'esp32_dispositivo_id' => $device->id,
            'latitude'             => -23.5,
        ]);
    }

    // --- Login emite token utilizável (Fase 1) ---

    public function test_login_issues_working_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('segredo123')]);

        $login = $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'segredo123',
        ])->assertStatus(200)->assertJsonPath('success', true);

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/esp32/fleet')
            ->assertStatus(200);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('segredo123')]);

        $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'errado',
        ])->assertStatus(422);
    }

    // --- Isolamento multi-tenant por empresa (Fase 4) ---

    public function test_usuario_nao_ve_dispositivo_de_outra_empresa(): void
    {
        Esp32Dispositivo::create([
            'empresa_id' => 2, 'identificador' => 'DEV-EMPRESA-2', 'ativo' => true,
        ]);

        Sanctum::actingAs(User::factory()->create(['empresa_id' => 1]), ['*']);

        // Não aparece no snapshot da frota.
        $this->getJson('/api/v1/esp32/fleet')
            ->assertStatus(200)
            ->assertJsonMissing(['identificador' => 'DEV-EMPRESA-2']);

        // Acesso direto ao recurso de outra empresa retorna 404.
        $this->getJson('/api/v1/esp32/dispositivos/DEV-EMPRESA-2')->assertStatus(404);
        $this->deleteJson('/api/v1/esp32/dispositivos/DEV-EMPRESA-2')->assertStatus(404);
    }

    public function test_admin_ve_todas_as_empresas(): void
    {
        Esp32Dispositivo::create([
            'empresa_id' => 2, 'identificador' => 'DEV-EMPRESA-2', 'ativo' => true,
        ]);

        $admin = User::factory()->create(['empresa_id' => 1]);
        $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/esp32/dispositivos/DEV-EMPRESA-2')->assertStatus(200);
    }

    public function test_cadastro_device_retorna_token_funcional(): void
    {
        Sanctum::actingAs(User::factory()->create(['empresa_id' => 1]), ['*']);

        $token = $this->postJson('/api/v1/esp32/dispositivos', ['identificador' => 'MAC-NEW'])
            ->assertStatus(201)
            ->json('device_token');

        $this->assertNotEmpty($token);

        // O token entregue no cadastro autentica a ingestão.
        $this->withHeader('X-DEVICE-TOKEN', $token)
            ->postJson('/api/v1/esp32/telemetry', ['lat' => -23.5])
            ->assertStatus(201);
    }
}
