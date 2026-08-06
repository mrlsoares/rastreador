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
        foreach (['super-admin', 'admin-empresa', 'operador'] as $role) {
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
    }
}
