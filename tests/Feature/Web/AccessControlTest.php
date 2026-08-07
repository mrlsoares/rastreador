<?php

namespace Tests\Feature\Web;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Controle de acesso da UI web (Fase 1 web): tudo exige login,
 * empresas só super-admin, usuários escopados por empresa.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'admin-empresa', 'operador'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Empresa::create(['id' => 1, 'razao_social' => 'Haufer', 'nome_fantasia' => 'Haufer', 'status' => 'ativo']);
        Empresa::create(['id' => 2, 'razao_social' => 'Outra', 'nome_fantasia' => 'Outra', 'status' => 'ativo']);
    }

    private function usuario(string $role, int $empresaId = 1): User
    {
        $u = User::factory()->create(['empresa_id' => $empresaId]);
        $u->syncRoles([$role]);

        return $u;
    }

    // --- Autenticação exigida em toda a UI ---

    public function test_visitante_e_redirecionado_ao_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/rastreadores')->assertRedirect('/login');
        $this->get('/mapa')->assertRedirect('/login');
        $this->get('/empresas')->assertRedirect('/login');
        $this->get('/usuarios')->assertRedirect('/login');
    }

    public function test_raiz_e_publica(): void
    {
        $this->get('/')->assertOk();
    }

    // --- Empresas: apenas super-admin ---

    public function test_super_admin_lista_empresas(): void
    {
        $this->actingAs($this->usuario('super-admin'))
            ->get('/empresas')->assertOk();
    }

    public function test_super_admin_cria_empresa(): void
    {
        $this->actingAs($this->usuario('super-admin'))
            ->post('/empresas', [
                'razao_social'  => 'Nova Empresa',
                'nome_fantasia' => 'Nova',
                'status'        => 'ativo',
            ])
            ->assertRedirect('/empresas');

        $this->assertDatabaseHas('empresas', ['razao_social' => 'Nova Empresa']);
    }

    public function test_admin_empresa_proibido_em_empresas(): void
    {
        $this->actingAs($this->usuario('admin-empresa'))
            ->get('/empresas')->assertForbidden();
    }

    public function test_operador_proibido_em_empresas(): void
    {
        $this->actingAs($this->usuario('operador'))
            ->get('/empresas')->assertForbidden();
    }

    // --- Usuários: super-admin ou admin-empresa, escopado por empresa ---

    public function test_admin_empresa_acessa_usuarios(): void
    {
        $this->actingAs($this->usuario('admin-empresa'))
            ->get('/usuarios')->assertOk();
    }

    public function test_operador_proibido_em_usuarios(): void
    {
        $this->actingAs($this->usuario('operador'))
            ->get('/usuarios')->assertForbidden();
    }

    public function test_admin_empresa_nao_edita_usuario_de_outra_empresa(): void
    {
        $ator  = $this->usuario('admin-empresa', 1);
        $alvo  = $this->usuario('operador', 2);

        $this->actingAs($ator)
            ->get("/usuarios/{$alvo->id}/edit")->assertForbidden();
    }

    public function test_admin_empresa_nao_cria_super_admin(): void
    {
        $ator = $this->usuario('admin-empresa', 1);

        $this->actingAs($ator)
            ->from('/usuarios/create')
            ->post('/usuarios', [
                'name'       => 'Fulano',
                'email'      => 'fulano@teste.local',
                'password'   => 'segredo',
                'empresa_id' => 1,
                'role'       => 'super-admin',
            ])
            ->assertRedirect('/usuarios/create')
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'fulano@teste.local']);
    }

    public function test_admin_empresa_cria_usuario_na_propria_empresa(): void
    {
        $ator = $this->usuario('admin-empresa', 1);

        // Mesmo pedindo empresa 2, deve cair na empresa do ator (1).
        $this->actingAs($ator)
            ->post('/usuarios', [
                'name'       => 'Ciclano',
                'email'      => 'ciclano@teste.local',
                'password'   => 'segredo',
                'empresa_id' => 2,
                'role'       => 'operador',
            ])
            ->assertRedirect('/usuarios');

        $this->assertDatabaseHas('users', [
            'email'      => 'ciclano@teste.local',
            'empresa_id' => 1,
        ]);
    }
}
