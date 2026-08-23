<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesEUsuarioAdminSeeder extends Seeder
{
    /**
     * Cria os papéis (spatie) e um usuário admin na empresa padrão (id=1).
     * Idempotente — pode rodar em produção sem duplicar.
     */
    public function run(): void
    {
        // 'leitor' = somente leitura global (app Windows de monitoramento de
        // tanques). Alcança apenas /esp32/{id}/historico e /esp32/{id}/ultima.
        foreach (['super-admin', 'admin-empresa', 'operador', 'leitor'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@rastreador.local'],
            [
                'name'       => 'Administrador',
                'empresa_id' => 1,
                'password'   => Hash::make('trocar-esta-senha'),
            ]
        );

        if (! $admin->hasRole('super-admin')) {
            $admin->syncRoles(['super-admin']);
        }

        // Usuário de máquina para o app Windows (monitor de abastecimento).
        // Sem empresa_id: enxerga a frota inteira (read-only global).
        $monitor = User::firstOrCreate(
            ['email' => 'monitor@haufer.com.br'],
            [
                'name'       => 'Monitor Tanques (Windows)',
                'empresa_id' => null,
                'password'   => Hash::make('trocar-esta-senha'),
            ]
        );

        if (! $monitor->hasRole('leitor')) {
            $monitor->syncRoles(['leitor']);
        }
    }
}
