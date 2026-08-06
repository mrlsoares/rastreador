<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SetupInicialSeeder extends Seeder
{
    /**
     * Empresas base, papéis e usuário dev. Idempotente (updateOrCreate).
     */
    public function run(): void
    {
        Empresa::updateOrCreate(
            ['id' => 1],
            ['razao_social' => 'Haufer', 'nome_fantasia' => 'Haufer', 'status' => 'ativo']
        );

        Empresa::updateOrCreate(
            ['id' => 2],
            ['razao_social' => 'Teste Desenvolvimento', 'nome_fantasia' => 'teste desenvolvimento', 'status' => 'ativo']
        );

        foreach (['super-admin', 'admin-empresa', 'operador'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $dev = User::updateOrCreate(
            ['email' => 'mrlsoares@gmail.com'],
            [
                'name'       => 'Marcos Soares',
                'empresa_id' => 1,
                'password'   => Hash::make('roda'),
            ]
        );

        if (! $dev->hasRole('super-admin')) {
            $dev->syncRoles(['super-admin']);
        }
    }
}
